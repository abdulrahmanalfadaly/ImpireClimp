(() => {
    const stage = document.getElementById('intro-stage');
    const messageEl = document.getElementById('intro-message');
    const skipButton = document.getElementById('skip-intro');
    const introAudio = document.getElementById('intro-audio');
    const stepper = document.querySelector('.stepper');
    const stepperSection = document.getElementById('character-creation');
    const stepElements = document.querySelectorAll('.step');
    const backBtn = document.querySelector('.step-back');
    const nextBtn = document.querySelector('.step-next');
    const lastStepInput = document.querySelector('#last_step');
    const syncFields = document.querySelectorAll('[data-target]');

    const narrativeLines = [
        { text: 'The world doesn’t care who you are.', animation: 'anim-fade-zoom', size: 'size-large', duration: 3200 },
        { text: 'This city is a machine.', animation: 'anim-glitch', size: 'size-extra-large', duration: 2200 },
        { text: 'Rent is due. Time is running.', animation: 'anim-slide', size: 'size-medium-large', duration: 3000 },
        { text: 'Every choice costs something.', animation: 'anim-scale', size: 'size-large', duration: 3200 },
        { text: 'One mistake can erase months.', animation: 'anim-fade', size: 'size-large', duration: 3600 },
        { text: 'No one is coming to save you.', animation: 'anim-shake', size: 'size-extra-large', duration: 3600 },
        { text: 'You weren’t born into power.', animation: 'anim-reveal', size: 'size-medium-large', duration: 3000 },
        { text: 'You will earn it.', animation: 'anim-pulse', size: 'size-very-large', duration: 2200 },
        { text: 'Money buys time. Time buys options.', animation: 'anim-cross', size: 'size-large', duration: 3200 },
        { text: 'Discipline is survival.', animation: 'anim-zoom', size: 'size-extra-large', duration: 3800 },
        { text: 'You will climb.', animation: 'anim-rise', size: 'size-very-large', duration: 3200 },
        { text: 'Build your empire.', animation: 'anim-glow', size: 'size-very-large', duration: 3400 },
        { text: 'Welcome to Empire Climb.', animation: 'anim-fade', size: 'size-maximum', duration: 3600 },
        { text: 'Initializing new identity…', animation: 'anim-typewriter', size: 'size-medium', typewriter: true, typeSpeed: 90 }
    ];

    let currentStep = 1;
    const totalSteps = stepElements.length;
    let currentLine = 0;
    let narrativeTimeout = 0;
    let typewriterTimer = 0;
    let narrativeComplete = false;

    const updateStep = () => {
        stepElements.forEach((step) => {
            const stepNumber = Number(step.dataset.step);
            step.classList.toggle('is-active', stepNumber === currentStep);
        });

        if (backBtn) {
            backBtn.disabled = currentStep === 1;
        }

        if (nextBtn) {
            if (currentStep === totalSteps) {
                nextBtn.textContent = 'Begin Simulation';
                nextBtn.type = 'submit';
            } else {
                nextBtn.textContent = 'Next';
                nextBtn.type = 'button';
            }
        }
    };

    const showStepper = () => {
        if (stepper) {
            stepper.classList.add('is-visible');
            updateStep();
        }
    };

    const finishIntro = () => {
        if (narrativeComplete) {
            return;
        }
        narrativeComplete = true;
        clearTimeout(narrativeTimeout);
        clearInterval(typewriterTimer);
        const spinner = document.querySelector('.intro-spinner');
        if (spinner) {
            spinner.remove();
        }
        if (stage) {
            stage.classList.add('intro-stage--hidden');
            stage.setAttribute('aria-hidden', 'true');
        }
        if (stepperSection) {
            stepperSection.classList.add('is-visible');
            stepperSection.removeAttribute('aria-hidden');
        }
        showStepper();
        if (introAudio) {
            introAudio.pause();
            introAudio.currentTime = 0;
        }
    };

    const highlightGold = new Set(['empire', 'earn', 'build', 'money', 'time', 'discipline', 'survival', 'climb', 'welcome']);
    const highlightRed = new Set(['machine', 'mistake', 'no', 'care', 'rent']);
    const animationClasses = ['word-float', 'word-glow', 'word-slide', 'word-twist'];

    const renderAnimatedLine = (line) => {
        if (!messageEl) {
            return;
        }
        const words = line.text.split(' ');
        const fragment = document.createDocumentFragment();
        words.forEach((word, index) => {
            const span = document.createElement('span');
            span.className = 'intro-word';
            const clean = word.replace(/[^a-zA-Z]/g, '').toLowerCase();
            if (highlightGold.has(clean)) {
                span.classList.add('word-gold');
            } else if (highlightRed.has(clean)) {
                span.classList.add('word-red');
            }
            const animation = animationClasses[Math.floor(Math.random() * animationClasses.length)];
            span.classList.add(animation);
            span.style.animationDelay = `${Math.random() * 0.6}s`;
            span.textContent = word;
            fragment.appendChild(span);
            if (index < words.length - 1) {
                fragment.appendChild(document.createTextNode(' '));
            }
        });
        messageEl.appendChild(fragment);
    };

    const displayLine = (line) => {
        if (!messageEl) {
            return;
        }
        messageEl.style.opacity = '0';
        messageEl.className = 'intro-message';
        messageEl.classList.add(line.animation, line.size);
        messageEl.textContent = '';

        if (line.typewriter) {
            let index = 0;
            typewriterTimer = window.setInterval(() => {
                if (index >= line.text.length) {
                    clearInterval(typewriterTimer);
                    const spinner = document.createElement('span');
                    spinner.className = 'intro-spinner';
                    messageEl.appendChild(spinner);
                    narrativeTimeout = window.setTimeout(() => {
                        spinner.remove();
                        finishIntro();
                    }, 2200);
                    return;
                }
                messageEl.textContent += line.text[index];
                index += 1;
            }, line.typeSpeed ?? 80);
            messageEl.style.opacity = '1';
            return;
        }

        renderAnimatedLine(line);
        messageEl.style.opacity = '1';
    };

    const playLine = () => {
        if (currentLine >= narrativeLines.length) {
            finishIntro();
            return;
        }
        const line = narrativeLines[currentLine];
        displayLine(line);
        if (line.typewriter) {
            return;
        }
        const hold = Math.max(1800, (line.duration ?? 3000) + (Math.random() - 0.5) * 600);
        narrativeTimeout = window.setTimeout(() => {
            currentLine += 1;
            playLine();
        }, hold);
    };

    const startNarrative = () => {
        if (introAudio) {
            introAudio.loop = true;
            introAudio.play().catch(() => {
                const resume = () => {
                    introAudio.play().catch(() => {});
                    document.removeEventListener('pointerdown', resume);
                };
                document.addEventListener('pointerdown', resume, { once: true });
            });
        }
        playLine();
    };

    const validateStep = () => {
        if (!stepper) {
            return true;
        }

        const activeStep = stepElements[currentStep - 1];
        const stepError = activeStep?.querySelector('.step-error');
        const setError = (text) => {
            if (stepError) {
                stepError.textContent = text;
            }
        };

        setError('');

        const charNameInput = document.querySelector('#character_name_input');
        const genderInput = document.querySelector('#gender_hidden');
        const ageInput = document.querySelector('#age_input');
        const countryInput = document.querySelector('#country_input');
        const lifeGoalInput = document.querySelector('#life_goal_input');

        const value = (input) => input?.value?.trim() ?? '';

        switch (currentStep) {
            case 1:
                if (value(charNameInput).length < 2 || value(charNameInput).length > 30) {
                    setError('Character name must be 2-30 characters.');
                    return false;
                }
                break;
            case 2:
                if (!value(genderInput)) {
                    setError('Select a gender.');
                    return false;
                }
                break;
            case 3:
                const age = Number(ageInput?.value);
                if (!age || age < 1) {
                    setError('Enter a valid age.');
                    return false;
                }
                break;
            case 4:
                if (!value(countryInput)) {
                    setError('Enter your country.');
                    return false;
                }
                break;
            case 5:
                if (!value(lifeGoalInput)) {
                    setError('A life goal is required.');
                    return false;
                }
                break;
            default:
                break;
        }

        return true;
    };

    const goToStep = (step) => {
        currentStep = Math.min(Math.max(step, 1), totalSteps);
        if (lastStepInput) {
            lastStepInput.value = currentStep;
        }
        updateStep();
    };

    const handleCardSelection = (selector, targetInputId) => {
        const cards = document.querySelectorAll(selector);
        const targetInput = document.querySelector(`#${targetInputId}`);

        cards.forEach((card) => {
            card.addEventListener('click', () => {
                cards.forEach((item) => item.classList.remove('is-active'));
                card.classList.add('is-active');
                if (targetInput) {
                    targetInput.value = card.dataset.value;
                }
            });
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        startNarrative();

        if (backBtn) {
            backBtn.addEventListener('click', () => goToStep(currentStep - 1));
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                if (nextBtn.type === 'submit') {
                    if (validateStep()) {
                        nextBtn.form?.requestSubmit();
                    }
                    return;
                }
                if (validateStep()) {
                    goToStep(currentStep + 1);
                }
            });
        }

        handleCardSelection('.gender-card', 'gender_hidden');

        syncFields.forEach((field) => {
            const target = document.querySelector(`#${field.dataset.target}`);
            if (!target) {
                return;
            }
            target.value = field.value;
            field.addEventListener('input', () => {
                target.value = field.value;
            });
        });

        const savedGender = document.querySelector('#gender_hidden')?.value;
        if (savedGender) {
            document.querySelectorAll('.gender-card').forEach((card) => {
                card.classList.toggle('is-active', card.dataset.value === savedGender);
            });
        }

        const savedStep = Number(lastStepInput?.value ?? 1);
        if (!Number.isNaN(savedStep) && savedStep >= 1 && savedStep <= totalSteps) {
            currentStep = savedStep;
        }

        if (skipButton) {
            skipButton.addEventListener('click', finishIntro);
        }
    });
})();

