const surveyCarouselEl = document.getElementById('surveyCarousel');
const surveyForm = document.querySelector('.survey-form');
const surveyCarousel = new bootstrap.Carousel(surveyCarouselEl, {
    touch: false,
    interval: false,
    wrap: false
});

let servicePage = 0;
let staffPage = 0;
let isSubmittingSurvey = false;
let ratingTimeout = null; // Added to prevent double-click jumping
let surveyStarted = false;
let submissionFailed = false;
let surveyWorkerRegistration = null;
let surveyCompleted = false;
let surveySubmissionId = null;
let surveyResetTimeout = null;
const QR_SLIDE_INDEX = 7;

let $currentServiceSet = $();
let $currentStaffSet = $();

function isLenovoTabM8Landscape() {
    return window.innerWidth >= 820 &&
        window.innerWidth <= 860 &&
        window.innerHeight >= 390 &&
        window.innerHeight <= 430;
}

function getStaffItemsPerPage() {
    if (isLenovoTabM8Landscape()) return 3;

    const width = window.innerWidth;

    if (width <= 640) return 3;
    if (width <= 920) return 3;
    return 3;
}

function getServiceItemsPerPage() {
    if (isLenovoTabM8Landscape()) return 8;

    const width = window.innerWidth;

    if (width <= 640) return 4;
    if (width <= 920) return 8;
    return 8;
}

function nextSlide() {
    surveyCarousel.next();
}

function prevSlide() {
    surveyCarousel.prev();
}

function showConnectivityNotice(message, canRetry = false) {
    document.getElementById('survey-connectivity-message').textContent = message;
    document.getElementById('survey-connectivity').classList.remove('is-hidden');
    document.getElementById('retrySurveySubmission').classList.toggle('is-hidden', !canRetry);
}

function updateConnectivityNotice() {
    if (submissionFailed) {
        showConnectivityNotice('Your response has not been confirmed as saved. When connected, press Submit survey to try again.', true);
    } else if (navigator.onLine === false) {
        showConnectivityNotice('You are offline. You can view the survey, but must reconnect before submitting.');
    } else if (!surveyForm.querySelector('input[name="_token"]').value) {
        showConnectivityNotice('You are viewing a saved survey. Submitting requires a connection to the survey server.');
    } else {
        document.getElementById('survey-connectivity').classList.add('is-hidden');
    }
}

async function submitForm() {
    if (isSubmittingSurvey || surveyCompleted) return;

    // Carousel indicators can skip questions; return to the first missing answer.
    const missingInput = Array.from(surveyForm.querySelectorAll('input[required]'))
        .find(input => !input.checkValidity());
    if (missingInput) {
        const slides = Array.from(surveyCarouselEl.querySelectorAll('.carousel-item'));
        surveyCarousel.to(slides.indexOf(missingInput.closest('.carousel-item')));
        showConnectivityNotice('Please answer every question before submitting.');
        return;
    }

    if (navigator.onLine === false) {
        submissionFailed = true;
        updateConnectivityNotice();
        return;
    }

    isSubmittingSurvey = true;
    document.getElementById('retrySurveySubmission').disabled = true;

    document.querySelectorAll('.rating-option').forEach(option => {
        option.style.pointerEvents = 'none';
        option.style.opacity = '0.7'; // Visual feedback that form is submitting
    });

    const controller = new AbortController();
    let requestTimeout = setTimeout(() => controller.abort(), 10000);
    try {
        // Never submit an offline HTML token: get the current session token from the network.
        const response = await fetch(surveyForm.dataset.csrfUrl, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' },
            signal: controller.signal
        });
        if (!response.ok || response.redirected) throw new Error('Token refresh failed');
        const data = await response.json();
        if (typeof data.token !== 'string' || !data.token) throw new Error('Missing survey token');

        surveyForm.querySelector('input[name="_token"]').value = data.token;
        clearTimeout(requestTimeout);
        requestTimeout = setTimeout(() => controller.abort(), 30000);

        // Stay in the same document so the kiosk keeps its fullscreen session.
        if (!surveySubmissionId) {
            const bytes = new Uint8Array(16);
            window.crypto.getRandomValues(bytes);
            bytes[6] = (bytes[6] & 15) | 64;
            bytes[8] = (bytes[8] & 63) | 128;
            const hex = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
            surveySubmissionId = hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' + hex.slice(16, 20) + '-' + hex.slice(20);
        }
        const payload = new FormData(surveyForm);
        payload.set('submission_id', surveySubmissionId);
        const savedResponse = await fetch(surveyForm.action, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' },
            body: payload,
            signal: controller.signal
        });
        if (savedResponse.redirected) throw new Error('Unexpected submission redirect');
        const result = await savedResponse.json();
        if (savedResponse.status === 422 && result.errors) {
            submissionFailed = true;
            const errors = Object.values(result.errors).reduce((all, messages) => all.concat(messages), []);
            showConnectivityNotice(errors.join(' ') || 'Please check your answers and try again.', true);
            return;
        }
        if (!savedResponse.ok || result.saved !== true) throw new Error('Save not confirmed');
        submissionFailed = false;
        surveyCompleted = true;
        const success = document.getElementById('success-message');
        success.textContent = result.message || 'Thank you for your feedback!';
        success.style.display = '';
        success.hidden = false;
        updateConnectivityNotice();
        surveyCarousel.to(QR_SLIDE_INDEX);
        scheduleSurveyReset();
    } catch (error) {
        submissionFailed = true;
        updateConnectivityNotice();
    } finally {
        isSubmittingSurvey = false;
        document.querySelectorAll('.rating-option').forEach(option => {
            option.style.pointerEvents = '';
            option.style.opacity = '';
        });
        clearTimeout(requestTimeout);
        document.getElementById('retrySurveySubmission').disabled = false;
    }
}

function scheduleSurveyReset() {
    clearTimeout(surveyResetTimeout);
    if (surveyCompleted) surveyResetTimeout = setTimeout(resetSurvey, 60 * 1000);
}

function resetSurvey() {
    if (isSubmittingSurvey) return;
    closeSurveyAppTools();
    clearTimeout(surveyResetTimeout);
    clearTimeout(ratingTimeout);
    surveyResetTimeout = null;
    ratingTimeout = null;
    // Preserve the current CSRF token while clearing the previous client's answers.
    const tokenInput = surveyForm.querySelector('input[name="_token"]');
    const token = tokenInput.value;
    surveyForm.reset();
    tokenInput.value = token;
    surveyForm.querySelectorAll('input[type="radio"]').forEach(input => { input.checked = false; });
    surveyForm.querySelectorAll('.division-btn, .staff-avatar, .service-item, .rating-option').forEach(item => {
        item.classList.remove('active', 'selected');
        item.style.pointerEvents = '';
        item.style.opacity = '';
    });
    surveyForm.querySelectorAll('.staff-item, .service-item').forEach(item => { item.style.display = 'none'; });
    $currentServiceSet = $();
    $currentStaffSet = $();
    servicePage = 0;
    staffPage = 0;
    ['staff-page-indicator', 'service-page-indicator'].forEach(id => {
        const indicator = document.getElementById(id);
        if (indicator) indicator.textContent = '';
    });
    surveyForm.querySelectorAll('.prev-staff-btn, .next-staff-btn, .prev-service-btn, .next-service-btn').forEach(button => {
        button.disabled = true;
        button.classList.add('disabled-visual');
    });
    const success = document.getElementById('success-message');
    success.hidden = true;
    success.textContent = '';
    surveyCompleted = false;
    surveySubmissionId = null;
    surveyStarted = false;
    submissionFailed = false;
    document.getElementById('retrySurveySubmission').disabled = false;
    surveyCarousel.to(0);
    const header = document.getElementById('surveyHeader');
    if (header) header.style.display = '';
    updateIndicators(0);
    updateConnectivityNotice();
    activateSurveyUpdateIfIdle();
}

function closeSurveyAppTools() {
    const tools = document.getElementById('surveyAppTools');
    if (tools) tools.open = false;
}

function activateSurveyUpdateIfIdle() {
    if (!surveyWorkerRegistration || !surveyWorkerRegistration.waiting || isSubmittingSurvey) return;
    const welcomeSlide = surveyCarouselEl.querySelector('.carousel-item');
    if ((!surveyStarted && welcomeSlide.classList.contains('active')) ||
        (surveyCompleted && document.getElementById('qr-timeout-slide').classList.contains('active'))) {
        surveyWorkerRegistration.waiting.postMessage({ type: 'ACTIVATE_SURVEY_UPDATE' });
    }
}

function updateNavButtonState($prevBtn, $nextBtn, currentPage, totalPages) {
    const isFirstPage = currentPage <= 0;
    const isLastPage = totalPages === 0 || currentPage >= totalPages - 1;

    $prevBtn
        .prop('disabled', isFirstPage)
        .toggleClass('disabled-visual', isFirstPage);

    $nextBtn
        .prop('disabled', isLastPage)
        .toggleClass('disabled-visual', isLastPage);
}

function renderStaffGrid() {
    const itemsPerPage = getStaffItemsPerPage();
    const totalItems = $currentStaffSet.length;
    const totalPages = Math.ceil(totalItems / itemsPerPage);

    if (staffPage >= totalPages && totalPages > 0) {
        staffPage = totalPages - 1;
    }

    $('.staff-item').hide();

    const start = staffPage * itemsPerPage;
    const end = start + itemsPerPage;
    const $visibleSlice = $currentStaffSet.slice(start, end);

    $visibleSlice.fadeIn(150);

    updateNavButtonState($('.prev-staff-btn'), $('.next-staff-btn'), staffPage, totalPages);

    $('#staff-page-indicator').text(totalPages > 0 ? `Page ${staffPage + 1} of ${totalPages}` : '');
}

function changeStaffPage(direction) {
    const itemsPerPage = getStaffItemsPerPage();
    const totalPages = Math.ceil($currentStaffSet.length / itemsPerPage);

    staffPage += direction;

    if (staffPage < 0) staffPage = 0;
    if (staffPage >= totalPages) staffPage = Math.max(totalPages - 1, 0);

    renderStaffGrid();
}

function renderServiceGrid() {
    const itemsPerPage = getServiceItemsPerPage();
    const totalItems = $currentServiceSet.length;
    const totalPages = Math.ceil(totalItems / itemsPerPage);

    if (servicePage >= totalPages && totalPages > 0) {
        servicePage = totalPages - 1;
    }

    $('.service-item').hide();

    const start = servicePage * itemsPerPage;
    const end = start + itemsPerPage;
    const $visibleSlice = $currentServiceSet.slice(start, end);

    $visibleSlice.fadeIn(150);

    updateNavButtonState($('.prev-service-btn'), $('.next-service-btn'), servicePage, totalPages);

    $('#service-page-indicator').text(totalPages > 0 ? `Page ${servicePage + 1} of ${totalPages}` : '');
}

function changeServicePage(direction) {
    const itemsPerPage = getServiceItemsPerPage();
    const totalPages = Math.ceil($currentServiceSet.length / itemsPerPage);

    servicePage += direction;

    if (servicePage < 0) servicePage = 0;
    if (servicePage >= totalPages) servicePage = Math.max(totalPages - 1, 0);

    renderServiceGrid();
}

function selectRating(selectedLabel, inputName, value, isLastQuestion = false) {
    if (isSubmittingSurvey) return;
    surveyStarted = true;

    const row = selectedLabel.closest('.rating-row');
    const labels = row.querySelectorAll('.rating-option');

    labels.forEach(label => label.classList.remove('active'));
    selectedLabel.classList.add('active');

    const input = selectedLabel.querySelector(`input[name="${inputName}"]`);
    if (input) input.checked = true;

    // BUG FIX 1: Clear any existing timeout. This prevents event bubbling
    // (label click + input click) from triggering the next slide twice.
    if (ratingTimeout) {
        clearTimeout(ratingTimeout);
    }

    if (isLastQuestion) {
        ratingTimeout = setTimeout(() => {
            submitForm();
        }, 300);
        return;
    }

    ratingTimeout = setTimeout(() => {
        nextSlide();
    }, 250);
}

function reactivateRating(slideElement) {
    const $slide = $(slideElement);
    const $checkedInput = $slide.find('input[type="radio"]:checked');

    $slide.find('.rating-option').removeClass('active');

    if ($checkedInput.length) {
        $checkedInput.closest('.rating-option').addClass('active');
    }
}

function toggleHeader(slideIndex) {
    const $header = $('#surveyHeader');
    slideIndex === 0 ? $header.show() : $header.hide();
}

function updateIndicators(currentSlideIndex) {
    const indicators = document.querySelectorAll('.carousel-indicators button');

    indicators.forEach((indicator, index) => {
        indicator.classList.remove('active');
        indicator.removeAttribute('aria-current');

        if (index === currentSlideIndex) {
            indicator.classList.add('active');
            indicator.setAttribute('aria-current', 'true');
        }
    });
}

function resetStaffSelection() {
    $('input[name="user_id"]').prop('checked', false);
    $('.staff-avatar').removeClass('selected');
}

function resetServiceSelection() {
    $('input[name="issue_id"]').prop('checked', false);
    $('.service-item').removeClass('selected');
}

function filterStaffByDivision(divisionId) {
    if (divisionId === 'all') {
        $currentStaffSet = $('.staff-item');
    } else {
        $currentStaffSet = $(`.staff-item[data-division-id="${divisionId}"]`);
    }

    staffPage = 0;
    resetStaffSelection();
    renderStaffGrid();
}

function filterServiceByDivision(divisionId) {
    $currentServiceSet = $(`.service-item[data-division-id="${divisionId}"]`);
    servicePage = 0;
    resetServiceSelection();
    renderServiceGrid();
}

function handleResize() {
    if ($('#staff-selection-slide').hasClass('active') && $currentStaffSet.length) {
        renderStaffGrid();
    }

    if ($('.service-slide').hasClass('active') && $currentServiceSet.length) {
        renderServiceGrid();
    }
}

$(document).ready(function () {
    toggleHeader(0);

    $('.carousel-indicators button').on('click', function () {
        const index = $(this).index();
        surveyCarousel.to(index);
    });

    $('.division-btn').on('click', function () {
        surveyStarted = true;
        const selectedDivisionId = String($(this).data('division-id'));

        $('.division-btn').removeClass('active');
        $(this).addClass('active');

        filterStaffByDivision(selectedDivisionId);
        $('#staff-selection-slide').data('current-division-id', selectedDivisionId);

        setTimeout(() => {
            nextSlide();
        }, 250);
    });

    $('.staff-item label').on('click', function (e) {
        e.preventDefault(); // Helps prevent double firing here too

        const $input = $(this).find('input[name="user_id"]');
        const $avatar = $(this).find('.staff-avatar');

        $('input[name="user_id"]').prop('checked', false);
        $('.staff-avatar').removeClass('selected');

        $input.prop('checked', true).trigger('change');
        $avatar.addClass('selected');

        setTimeout(() => {
            nextSlide();
        }, 250);
    });

    $('input[name="user_id"]').on('change', function () {
        const selectedDivisionId = String($(this).data('division-id'));
        filterServiceByDivision(selectedDivisionId);
    });

    $('input[name="issue_id"]').on('change', function () {
        $('.service-item').removeClass('selected');
        $(this).closest('.service-item').addClass('selected');

        setTimeout(() => {
            nextSlide();
        }, 250);
    });

    $('#surveyCarousel').on('slid.bs.carousel', function (e) {
        const currentSlideIndex = e.to;
        const $currentSlide = $(e.relatedTarget);

        toggleHeader(currentSlideIndex);
        updateIndicators(currentSlideIndex);
        reactivateRating(e.relatedTarget);

        if ($currentSlide.attr('id') === 'staff-selection-slide' && $currentStaffSet.length) {
            renderStaffGrid();
        }

        if ($currentSlide.hasClass('service-slide') && $('input[name="user_id"]:checked').length > 0) {
            renderServiceGrid();
        }

        activateSurveyUpdateIfIdle();
    });

    $(window).on('resize orientationchange', function () {
        handleResize();
    });

    setTimeout(() => {
        $('#success-message').fadeOut('slow');
    }, 5000);
});

document.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    const qrSlide = document.getElementById('qr-timeout-slide');
    const successMessage = document.getElementById('success-message'); // BUG FIX 2: Look for the message

    updateConnectivityNotice();
    window.addEventListener('online', updateConnectivityNotice);
    window.addEventListener('offline', updateConnectivityNotice);
    surveyForm.addEventListener('change', function () { surveyStarted = true; });
    surveyForm.addEventListener('submit', function (event) {
        event.preventDefault();
        submitForm();
    });

    // BUG FIX 2: If the URL has 'thank_you' OR Laravel rendered the success message into the DOM,
    // jump immediately to the QR slide so the user can actually see the message!
    const isCachedSurvey = !surveyForm.querySelector('input[name="_token"]').value;
    const isThankYouPage = !isCachedSurvey && (urlParams.has('thank_you') || Boolean(successMessage && !successMessage.hidden && successMessage.textContent.trim()));
    surveyCompleted = isThankYouPage;
    if (isThankYouPage && surveyCarouselEl) {
        if (urlParams.has('thank_you')) {
            history.replaceState({}, document.title, window.location.pathname);
        }

        // Remove transitions temporarily so the jump is instant on page load
        document.querySelector('.carousel-inner').style.transition = 'none';
        surveyCarousel.to(QR_SLIDE_INDEX);
        scheduleSurveyReset();

        setTimeout(() => {
             document.querySelector('.carousel-inner').style.transition = '';
        }, 50);
    }

    if (surveyCarouselEl && qrSlide) {
        surveyCarouselEl.addEventListener('slid.bs.carousel', function () {
            if (qrSlide.classList.contains('active')) {
                scheduleSurveyReset();
            } else {
                clearTimeout(surveyResetTimeout);
            }
        });
    }

    if ('serviceWorker' in navigator) {
        // Warm the normal document once on first control; never reload an active survey.
        if (!navigator.serviceWorker.controller && !isThankYouPage && !window.location.search) {
            navigator.serviceWorker.addEventListener('controllerchange', function () {
                fetch('/survey', { credentials: 'same-origin', cache: 'no-store' }).catch(function () {
                    // The precached offline page remains available if the server is unreachable.
                });
            }, { once: true });
        }

        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw-survey.js', {
                scope: '/survey',
                updateViaCache: 'none'
            }).then(function (registration) {
                surveyWorkerRegistration = registration;
                activateSurveyUpdateIfIdle();
                function watchInstallingWorker() {
                    const worker = registration.installing;
                    if (!worker) return;
                    worker.addEventListener('statechange', function () {
                        if (worker.state === 'installed') activateSurveyUpdateIfIdle();
                    });
                }
                registration.addEventListener('updatefound', watchInstallingWorker);
                watchInstallingWorker();
            }).catch(function (error) {
                console.error('Service worker registration failed:', error);
            });
        });
    }

    const fullscreenBtn = document.getElementById('fullscreenSurvey');
    const fullscreenLabel = document.getElementById('surveyFullscreenLabel');
    const fullscreenMessage = document.getElementById('surveyFullscreenMessage');
    const appTools = document.getElementById('surveyAppTools');
    if (appTools) {
        document.addEventListener('click', function (event) {
            if (!appTools.contains(event.target)) closeSurveyAppTools();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeSurveyAppTools();
        });
    }

    function currentFullscreenElement() {
        return document.fullscreenElement || document.webkitFullscreenElement;
    }

    function updateFullscreenControls() {
        const active = Boolean(currentFullscreenElement());
        fullscreenLabel.textContent = active ? 'Exit Fullscreen' : 'Enter Fullscreen';
        fullscreenBtn.setAttribute('aria-pressed', String(active));
    }

    function showFullscreenMessage(message) {
        fullscreenMessage.textContent = message;
        fullscreenMessage.hidden = false;
    }

    fullscreenBtn.addEventListener('click', async function () {
        if (fullscreenBtn.disabled) return;
        closeSurveyAppTools();
        fullscreenMessage.hidden = true;
        const root = document.documentElement;
        const active = Boolean(currentFullscreenElement());
        const method = active
            ? (document.exitFullscreen || document.webkitExitFullscreen)
            : (root.requestFullscreen || root.webkitRequestFullscreen);
        if (!method || (!active && (document.fullscreenEnabled === false || document.webkitFullscreenEnabled === false))) {
            showFullscreenMessage('This browser does not allow fullscreen for this page. Try an up-to-date browser. Fullscreen does not require installing the app.');
            return;
        }
        fullscreenBtn.disabled = true;
        try {
            // Request immediately from the tap; browsers require user activation.
            if (active) {
                await method.call(document);
            } else if (root.requestFullscreen) {
                await method.call(root, { navigationUI: 'hide' });
            } else {
                await method.call(root);
            }
        } catch (error) {
            showFullscreenMessage('The browser could not enter or exit fullscreen. Tap the button to try again, or try another browser. Your survey answers have not been changed.');
        } finally {
            fullscreenBtn.disabled = false;
            updateFullscreenControls();
        }
    });
    function fullscreenChanged() {
        fullscreenMessage.hidden = true;
        updateFullscreenControls();
    }
    document.addEventListener('fullscreenchange', fullscreenChanged);
    document.addEventListener('webkitfullscreenchange', fullscreenChanged);
    updateFullscreenControls();

    let deferredPrompt = null;
    const installBtn = document.getElementById('installSurveyApp');
    const installNowBtn = document.getElementById('installSurveyNow');
    const installHelpEl = document.getElementById('surveyInstallHelp');
    const installHelpMessage = document.getElementById('surveyInstallHelpMessage');
    const standaloneMode = window.matchMedia ? window.matchMedia('(display-mode: standalone)') : null;
    let surveyInstalled = Boolean((standaloneMode && standaloneMode.matches) || navigator.standalone);

    function updateInstallControls() {
        installBtn.hidden = surveyInstalled;
        installNowBtn.classList.toggle('is-hidden', !deferredPrompt || surveyInstalled);
        if (window.isSecureContext === false) {
            installHelpMessage.textContent = 'Installation requires a secure connection. Open the HTTPS survey address on this tablet.';
        } else if (deferredPrompt) {
            installHelpMessage.textContent = 'HRDO Survey is ready to install. Tap Install now and confirm in your browser.';
        } else {
            installHelpMessage.textContent = 'Chrome has not offered installation yet. Keep this page open for a moment, then try Install App again. Your browser must offer installation before this page can open its install prompt.';
        }
    }

    function showInstallHelp() {
        updateInstallControls();
        bootstrap.Modal.getOrCreateInstance(installHelpEl).show();
    }

    async function installSurvey() {
        if (surveyInstalled || installBtn.disabled) return;
        closeSurveyAppTools();
        if (!deferredPrompt) {
            showInstallHelp();
            return;
        }

        const prompt = deferredPrompt;
        deferredPrompt = null;
        installBtn.disabled = true;
        installNowBtn.disabled = true;
        const helpModal = bootstrap.Modal.getInstance(installHelpEl);
        if (helpModal) helpModal.hide();
        let promptFailed = false;
        try {
            // A captured browser event can be prompted only once, from this user click.
            await prompt.prompt();
            await prompt.userChoice;
        } catch (error) {
            promptFailed = true;
        } finally {
            installBtn.disabled = false;
            installNowBtn.disabled = false;
            updateInstallControls();
            if (promptFailed) {
                showInstallHelp();
                installHelpMessage.textContent = 'The browser could not open installation. Refresh the survey and tap Install App again.';
            }
        }
    }

    updateInstallControls();
    installBtn.addEventListener('click', installSurvey);
    installNowBtn.addEventListener('click', installSurvey);

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;
        updateInstallControls();
    });

    window.addEventListener('appinstalled', function () {
        deferredPrompt = null;
        surveyInstalled = true;
        updateInstallControls();
        const helpModal = bootstrap.Modal.getInstance(installHelpEl);
        if (helpModal) helpModal.hide();
    });

    if (standaloneMode && standaloneMode.addEventListener) {
        standaloneMode.addEventListener('change', function (event) {
            surveyInstalled = event.matches || Boolean(navigator.standalone);
            updateInstallControls();
        });
    }
});
