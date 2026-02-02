<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/topbar.php';
require_once __DIR__ . '/includes/migration.php';

require_login();
$user = current_user();

if ($user['role'] !== 'admin') {
    header('Location: ' . site_url('/dashboard.php'));
    exit;
}

$pdo = get_pdo();
$q = trim($_GET['q'] ?? '');
$ajax = $_GET['ajax'] ?? '';
$selectedId = (int)($_GET['user_id'] ?? 0);
$listAll = ($_GET['list_all'] ?? '') === '1';

$profileColumns = $pdo->query("SHOW COLUMNS FROM player_profile")->fetchAll(PDO::FETCH_COLUMN);
$stateColumns = $pdo->query("SHOW COLUMNS FROM game_state")->fetchAll(PDO::FETCH_COLUMN);
$statsColumns = [];
$statsUserIdCol = 'user_id';
try {
    $statsColumns = $pdo->query("SHOW COLUMNS FROM player_stats")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    // Table might not exist yet; stats will be created when migrations run.
}
$profileUserIdCol = in_array('user_id', $profileColumns, true) ? 'user_id' : (in_array('player_id', $profileColumns, true) ? 'player_id' : 'user_id');
$stateUserIdCol = in_array('user_id', $stateColumns, true) ? 'user_id' : (in_array('player_id', $stateColumns, true) ? 'player_id' : 'user_id');
$statsUserIdCol = in_array('user_id', $statsColumns, true) ? 'user_id' : (in_array('player_id', $statsColumns, true) ? 'player_id' : 'user_id');
$profileSearchCols = array_values(array_filter(
    array_merge(['character_name'], ['name', 'full_name', 'display_name']),
    static fn(string $col) => in_array($col, $profileColumns, true)
));

$allowedUserFields = ['username', 'role', 'created_at', 'last_login'];
$allowedProfileFields = ['character_name', 'gender', 'age', 'country', 'life_goal'];
$allowedStateFields = ['money', 'balance'];
$allowedStatsFields = $statsColumns ? ['health', 'energy', 'happiness'] : [];

if ($ajax === 'search') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        if (!$listAll && $q === '') {
            echo json_encode([]);
            exit;
        }

        $bindings = [];
        $profileSelects = [];
        foreach ($profileSearchCols as $col) {
            $profileSelects[] = "NULLIF(pp.{$col}, '')";
        }

        $nameExpression = $profileSelects
            ? 'COALESCE(' . implode(', ', $profileSelects) . ')'
            : 'NULL';

        if ($listAll) {
            $whereClauses = ['1=1'];
            $limit = 200;
        } else {
            $like = '%' . $q . '%';
            $bindings[':username'] = $like;
            $whereClauses = ['u.username LIKE :username'];
            foreach ($profileSearchCols as $index => $col) {
                $param = ":profile_{$index}";
                $whereClauses[] = "pp.{$col} LIKE {$param}";
                $bindings[$param] = $like;
            }
            $limit = 25;
        }

        $sql = "SELECT u.id, u.username, {$nameExpression} AS name
                FROM users u
                LEFT JOIN player_profile pp ON pp.{$profileUserIdCol} = u.id
                WHERE " . implode(' OR ', $whereClauses) . "
                GROUP BY u.id
                ORDER BY u.username ASC
                LIMIT {$limit}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;

    } catch (Throwable $e) {
        // IMPORTANT: never output HTML/warnings to the fetch() json parser
        http_response_code(200);
        echo json_encode([
            '_error' => true,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

if ($ajax === 'user_data' && $selectedId > 0) {
    ensure_game_state($selectedId);
    ensure_player_profile($selectedId);
    $userStmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
    $userStmt->execute([':id' => $selectedId]);
    $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
    $profileStmt = $pdo->prepare("SELECT * FROM player_profile WHERE {$profileUserIdCol} = :id LIMIT 1");
    $profileStmt->execute([':id' => $selectedId]);
    $profileRow = $profileStmt->fetch(PDO::FETCH_ASSOC);
    $stateStmt = $pdo->prepare("SELECT * FROM game_state WHERE {$stateUserIdCol} = :id LIMIT 1");
    $stateStmt->execute([':id' => $selectedId]);
    $stateRow = $stateStmt->fetch(PDO::FETCH_ASSOC);
    $statsRow = null;

    if (!empty($statsColumns)) {
        ensure_player_stats($selectedId);
        $statsStmt = $pdo->prepare("SELECT * FROM player_stats WHERE {$statsUserIdCol} = :id LIMIT 1");
        $statsStmt->execute([':id' => $selectedId]);
        $statsRow = $statsStmt->fetch(PDO::FETCH_ASSOC);
    }

    $sanitize = function (?array $row) {
        if (!$row) {
            return null;
        }
        foreach ($row as $key => $value) {
            if (preg_match('/password|hash|token|secret/i', $key)) {
                $row[$key] = '[hidden]';
            }
        }
        return $row;
    };

    echo json_encode([
        'user' => $sanitize($userRow),
        'profile' => $sanitize($profileRow),
        'state' => $sanitize($stateRow),
        'stats' => $sanitize($statsRow),
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
    } catch (RuntimeException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }

    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($action === 'migrate') {
        $logs = [];

        try {
            run_migrations($pdo, $logs);
            echo json_encode(['status' => 'success', 'logs' => $logs]);
        } catch (Throwable $e) {
            $logs[] = '[FAIL] ' . $e->getMessage();
            echo json_encode(['status' => 'error', 'message' => 'Migration failed', 'logs' => $logs]);
        }

        exit;
    }

    if ($action === 'update_field') {
        $source = $_POST['source'] ?? '';
        $field = $_POST['field'] ?? '';
        $value = $_POST['value'] ?? '';

        $allowed = [];
        if ($source === 'users') $allowed = $allowedUserFields;
        if ($source === 'profile') $allowed = $allowedProfileFields;
        if ($source === 'state') $allowed = $allowedStateFields;
        if ($source === 'stats') $allowed = $allowedStatsFields;

        if (!in_array($field, $allowed, true) || $userId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid field']);
            exit;
        }

        $table = 'users';
        $keyColumn = 'id';

        if ($source === 'profile') {
            $table = 'player_profile';
            $keyColumn = $profileUserIdCol;
        } elseif ($source === 'state') {
            $table = 'game_state';
            $keyColumn = $stateUserIdCol;
        } elseif ($source === 'stats') {
            $table = 'player_stats';
            $keyColumn = $statsUserIdCol;
        }

        if ($source === 'profile') {
            ensure_player_profile($userId);
        }

        if ($source === 'state') {
            ensure_game_state($userId);
        }

        if ($source === 'stats') {
            ensure_player_stats($userId);
        }

        $stmt = $pdo->prepare("UPDATE {$table} SET {$field} = :value WHERE {$keyColumn} = :id");
        $stmt->execute([':value' => $value, ':id' => $userId]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'reset_password') {
        $newPassword = $_POST['new_password'] ?? '';
        if ($userId <= 0 || $newPassword === '') {
            echo json_encode(['status' => 'error', 'message' => 'Missing password']);
            exit;
        }
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $stmt->execute([':hash' => $hash, ':id' => $userId]);
        echo json_encode(['status' => 'success']);
        exit;
    }
}

render_header('Control Room');
render_topbar('admin');
$csrfToken = csrf_token();
?>

<div class="admin-grid">
    <section class="admin-panel" data-anim data-delay="0">
        <h2>Player Control</h2>
        <div class="admin-search">
            <div class="admin-search-row">
                <input type="text" id="admin-search" class="admin-input" placeholder="Search by identifier or name" value="<?= escape($q) ?>">
                <button type="button" id="admin-list-all" class="btn btn-ghost">Browse all</button>
            </div>
        </div>
        <div class="admin-migration">
            <button type="button" id="admin-migrate" class="btn btn-warning">Rebuild / Upgrade Database</button>
            <div id="migration-console" class="migration-console" aria-live="polite">
                <pre id="migration-log" class="migration-console-log" aria-live="polite"></pre>
            </div>
        </div>
        <div id="admin-results" class="admin-results">
        </div>
        <div class="admin-all-block">
            <div id="admin-all-results" class="admin-results">
            </div>
        </div>
    </section>

    <section class="admin-preview" data-anim data-delay="0.3">
        <h2>Player Preview</h2>
        <div id="preview-content" class="admin-preview-content">
            <p class="admin-preview-empty">Select a player to see details.</p>
        </div>
    </section>
</div>

<script>
 (function () {
    const timeoutDelay = 150;
    const searchInput = document.getElementById('admin-search');
    const resultsContainer = document.getElementById('admin-results');
    const allResultsContainer = document.getElementById('admin-all-results');
    const previewContainer = document.getElementById('preview-content');
    const listAllButton = document.getElementById('admin-list-all');
    const csrf = '<?= escape($csrfToken) ?>';
    let timeout = null;
    let selectedUserId = null;

    const createResultButton = (item) => {
        const line = document.createElement('button');
        line.type = 'button';
        line.className = 'admin-result';
        const displayName = item.name || '—';
        line.innerHTML = `<span class="admin-result-line">${item.username} | ${displayName}</span>`;
        line.addEventListener('click', () => {
            selectedUserId = item.id;
            fetchUser(item.id);
        });
        return line;
    };

    const renderList = (items, container, emptyText) => {
        if (!items.length) {
            container.innerHTML = `<p class="admin-preview-empty">${emptyText}</p>`;
            if (container === resultsContainer) {
                previewContainer.innerHTML = '<p class="admin-preview-empty">Select a player to see details.</p>';
            }
            return;
        }

        container.innerHTML = '';
        items.forEach(item => container.appendChild(createResultButton(item)));
    };

    const buildField = (source, key, label, value) => {
        const field = document.createElement('div');
        field.className = 'admin-preview-field';
        const labelEl = document.createElement('span');
        labelEl.className = 'admin-preview-label';
        labelEl.textContent = label;
        const input = document.createElement('input');
        input.className = 'admin-inline-input';
        input.dataset.source = source;
        input.dataset.field = key;
        input.value = value ?? '';
        field.appendChild(labelEl);
        field.appendChild(input);
        return field;
    };

    const renderSection = (title, fields) => {
        const section = document.createElement('div');
        section.className = 'admin-preview-section';
        const heading = document.createElement('h3');
        heading.textContent = title;
        section.appendChild(heading);
        if (!fields.length) {
            const empty = document.createElement('p');
            empty.className = 'admin-preview-empty';
            empty.textContent = 'No record found.';
            section.appendChild(empty);
        } else {
            fields.forEach(field => section.appendChild(field));
        }
        return section;
    };

    const renderPreview = (data) => {
        previewContainer.innerHTML = '';
        if (!data || !data.user) {
            previewContainer.innerHTML = '<p class="admin-preview-empty">Select a player to see details.</p>';
            return;
        }

        const userFields = Object.entries(data.user).map(([k, v]) => buildField('users', k.toLowerCase(), k.toUpperCase(), v));
        const profileFields = data.profile ? Object.entries(data.profile).map(([k, v]) => buildField('profile', k.toLowerCase(), k.toUpperCase(), v)) : [];
        const stateFields = data.state ? Object.entries(data.state).map(([k, v]) => buildField('state', k.toLowerCase(), k.toUpperCase(), v)) : [];
        const statsFields = data.stats ? Object.entries(data.stats).map(([k, v]) => buildField('stats', k.toLowerCase(), k.toUpperCase(), v)) : [];

        previewContainer.appendChild(renderSection('Users', userFields));
        previewContainer.appendChild(renderSection('Player Profile', profileFields));
        previewContainer.appendChild(renderSection('Game State', stateFields));
        previewContainer.appendChild(renderSection('Player Stats', statsFields));

        const resetSection = document.createElement('div');
        resetSection.className = 'admin-preview-section';
        const resetHeading = document.createElement('h3');
        resetHeading.textContent = 'Reset Password';
        resetSection.appendChild(resetHeading);
        const resetInput = document.createElement('input');
        resetInput.id = 'reset-password';
        resetInput.className = 'admin-input';
        resetInput.type = 'password';
        resetInput.placeholder = 'New password';
        resetSection.appendChild(resetInput);
        previewContainer.appendChild(resetSection);

        const message = document.createElement('div');
        message.id = 'preview-message';
        message.className = 'admin-preview-message';
        previewContainer.appendChild(message);

        previewContainer.querySelectorAll('.admin-inline-input').forEach(input => {
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    updateField(input);
                }
            });
            input.addEventListener('blur', () => updateField(input));
        });

        resetInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                resetPassword();
            }
        });
        resetInput.addEventListener('blur', resetPassword);
    };

    const updateField = (input) => {
        if (!selectedUserId) return;
        fetch('<?= site_url('/admin.php') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'update_field',
                user_id: selectedUserId,
                source: input.dataset.source,
                field: input.dataset.field,
                value: input.value,
                csrf_token: csrf
            })
        }).then(res => res.json()).then(response => {
            const message = document.getElementById('preview-message');
            if (message) {
                message.textContent = response.status === 'success' ? 'Saved' : response.message || 'Update failed';
                message.className = `admin-preview-message ${response.status}`;
            }
        });
    };

    const resetPassword = () => {
        if (!selectedUserId) return;
        const password = document.getElementById('reset-password')?.value.trim();
        if (!password) return;
        fetch('<?= site_url('/admin.php') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'reset_password',
                user_id: selectedUserId,
                new_password: password,
                csrf_token: csrf
            })
        }).then(res => res.json()).then(response => {
            const message = document.getElementById('preview-message');
            if (message) {
                message.textContent = response.status === 'success' ? 'Password reset' : response.message || 'Reset failed';
                message.className = `admin-preview-message ${response.status}`;
            }
        });
    };

    const fetchResults = (query) => {
        fetch(`<?= site_url('/admin.php') ?>?ajax=search&q=${encodeURIComponent(query)}`)
            .then(async (res) => {
                const text = await res.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Search response not JSON:', text);
                    throw e;
                }
            })
            .then((data) => {
                if (data && data._error) {
                    console.error('Search PHP error:', data.message);
                    resultsContainer.innerHTML = `<p class="admin-preview-empty">Search error (check console)</p>`;
                    return;
                }
                renderList(data, resultsContainer, 'No players found.');
            })
            .catch((err) => {
                console.error('Search fetch failed:', err);
                resultsContainer.innerHTML = `<p class="admin-preview-empty">Search failed (check console)</p>`;
            });
    };

    const fetchAllPlayers = () => {
        if (!allResultsContainer) {
            return;
        }
        allResultsContainer.innerHTML = '<p class="admin-preview-empty">Loading players...</p>';
        fetch(`<?= site_url('/admin.php') ?>?ajax=search&list_all=1`)
            .then(async (res) => {
                const text = await res.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Search response not JSON:', text);
                    throw e;
                }
            })
            .then((data) => {
                if (data && data._error) {
                    console.error('Search PHP error:', data.message);
                    allResultsContainer.innerHTML = `<p class="admin-preview-empty">Search error (check console)</p>`;
                    return;
                }
                renderList(data, allResultsContainer, 'No players found.');
            })
            .catch((err) => {
                console.error('List-all fetch failed:', err);
                allResultsContainer.innerHTML = `<p class="admin-preview-empty">Failed to load players.</p>`;
            })
            .finally(() => {
                if (listAllButton) {
                    listAllButton.disabled = false;
                }
            });
    };

    const fetchUser = (id) => {
        fetch(`<?= site_url('/admin.php') ?>?ajax=user_data&user_id=${encodeURIComponent(id)}`)
            .then(res => res.json())
            .then(renderPreview);
    };

    if (listAllButton) {
        listAllButton.addEventListener('click', () => {
            listAllButton.disabled = true;
            fetchAllPlayers();
        });
    }

    searchInput.addEventListener('input', () => {
        const value = searchInput.value.trim();
        if (timeout) clearTimeout(timeout);
        if (!value) {
            resultsContainer.innerHTML = '<p class="admin-preview-empty">Type to search players...</p>';
            previewContainer.innerHTML = '<p class="admin-preview-empty">Select a player to see details.</p>';
            return;
        }
        timeout = setTimeout(() => fetchResults(value), timeoutDelay);
    });

    const migrationButton = document.getElementById('admin-migrate');
    const migrationLog = document.getElementById('migration-log');
    const migrationConsole = document.getElementById('migration-console');

    const renderMigrationLog = (lines = []) => {
        if (!migrationLog) {
            return;
        }

        if (!lines.length) {
            migrationConsole?.classList.remove('has-data');
            migrationLog.textContent = 'Migration logs will appear here.';
            return;
        }

        migrationConsole?.classList.add('has-data');
        migrationLog.textContent = lines.join('\n');
    };

    if (migrationButton) {
        migrationButton.addEventListener('click', () => {
            if (!confirm('This will rebuild or upgrade the database schema. Proceed?')) {
                return;
            }

            migrationButton.disabled = true;
            renderMigrationLog(['Running migrations...']);

            fetch('<?= site_url('/admin.php') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'migrate',
                    csrf_token: csrf
                })
            }).then(res => res.json()).then(response => {
                const lines = Array.isArray(response.logs) ? [...response.logs] : [];
                if (response.status !== 'success') {
                    lines.push(response.message ? `[ERROR] ${response.message}` : '[ERROR] Migration failed');
                }
                renderMigrationLog(lines);
            }).catch((err) => {
                console.error('Migration fetch failed:', err);
                renderMigrationLog([`[ERROR] ${err.message || 'Request failed'}`]);
            }).finally(() => {
                migrationButton.disabled = false;
            });
        });
    }
})();
</script>

<?php
render_footer();
 