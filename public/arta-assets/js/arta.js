const surveyCarouselEl = document.getElementById('surveyCarousel');
const surveyCarousel = new bootstrap.Carousel(surveyCarouselEl, {
    touch: false,
    interval: false,
    wrap: false
});

let isSubmittingSurvey = false;

// --- VALIDATION ENGINE ---
function validateCurrentSlide() {
    const $currentSlide = $('.carousel-item.active');
    let isValid = true;

    // 1. Validate Text, Number, and Select inputs
    $currentSlide.find('input[type="text"], input[type="number"], select').each(function() {
        let val = $(this).val();
        let fieldIsValid = true;

        // Check if empty
        if (!val || val.toString().trim() === '') {
            fieldIsValid = false;
        }
        // Specific check for number limits and whole numbers
        else if ($(this).attr('type') === 'number') {
            let numVal = Number(val);
            let min = parseInt($(this).attr('min')) || 1;
            let max = parseInt($(this).attr('max')) || 150;

            if (!Number.isInteger(numVal) || numVal < min || numVal > max) {
                fieldIsValid = false;
            }
        }

        if (!fieldIsValid) {
            isValid = false;
            $(this).css('border-color', '#dc3545'); // Apply red border
        } else {
            $(this).css('border-color', ''); // Reset border
        }
    });

    // 2. Validate Radio button groups (Client Type, Sex, CC, Emojis)
    const radioNames = new Set();
    $currentSlide.find('input[type="radio"]').each(function() {
        radioNames.add($(this).attr('name'));
    });

    radioNames.forEach(name => {
        if ($currentSlide.find(`input[name="${name}"]:checked`).length === 0) {
            isValid = false;
        }
    });

    return isValid;
}

// Shows a red warning text if validation fails
function showSlideError() {
    const $currentSlide = $('.carousel-item.active');
    let $error = $currentSlide.find('.slide-error');

    // Create the error message if it doesn't exist on this slide yet
    if ($error.length === 0) {
        $error = $('<div class="slide-error text-danger fw-bold mb-3" style="display:none; font-size:1.1rem;">⚠️ Please provide a valid answer to proceed.</div>');
        $currentSlide.find('.nav-buttons').before($error); // Insert right above the Next button
    }
    $error.fadeIn(200);
}

function hideSlideError() {
    $('.carousel-item.active').find('.slide-error').fadeOut(200);
}
// ----------------------------

function nextSlide() {
    // Only proceed if the current slide passes validation
    if (validateCurrentSlide()) {
        hideSlideError();
        surveyCarousel.next();
    } else {
        showSlideError();
    }
}

function prevSlide() {
    hideSlideError();
    surveyCarousel.prev();
}

function submitForm() {
    // Prevent submitting if the last question is skipped
    if (!validateCurrentSlide()) {
        showSlideError();
        return;
    }

    if (isSubmittingSurvey) return;
    isSubmittingSurvey = true;

    // Prevent double clicking on UI while submitting
    document.querySelectorAll('.rating-option, .btn, .btn-check').forEach(option => {
        option.style.pointerEvents = 'none';
        option.classList.add('disabled');
    });

    document.querySelector('form').submit();
}

// Logic for handling the Emoji selections
function selectRating(selectedLabel, inputName, value, isLastQuestion = false) {
    if (isSubmittingSurvey) return;

    const row = selectedLabel.closest('.rating-row');
    const labels = row.querySelectorAll('.rating-option');

    labels.forEach(label => label.classList.remove('active'));
    selectedLabel.classList.add('active');

    const input = selectedLabel.querySelector(`input[name="${inputName}"]`);
    if (input) input.checked = true;

    hideSlideError(); // Immediately hide the error once they make a choice
}

// Sync the dots with the current slide
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

// Re-highlight the selected emoji if the user goes back to a previous slide
function reactivateRating(slideElement) {
    const $slide = $(slideElement);
    const $checkedInput = $slide.find('input[type="radio"]:checked');

    $slide.find('.rating-option').removeClass('active');

    if ($checkedInput.length) {
        $checkedInput.closest('.rating-option').addClass('active');
    }
}

$(document).ready(function () {
    // Handle fading out success session messages
    setTimeout(() => {
        $('#success-message').fadeOut('slow');
    }, 5000);

    // Carousel transition hook
    $('#surveyCarousel').on('slid.bs.carousel', function (e) {
        reactivateRating(e.relatedTarget);
        updateIndicators(e.to); // Sync the dots!
    });

    // Remove red borders and hide errors instantly as the user types or selects dropdowns
    $(document).on('input change', 'input, select', function() {
        $(this).css('border-color', '');
        hideSlideError();
    });
});

// Service worker and QR reset logic
document.addEventListener('DOMContentLoaded', function () {
    const QR_SLIDE_INDEX = document.querySelectorAll('.carousel-item').length - 1;
    const urlParams = new URLSearchParams(window.location.search);
    const qrSlide = document.getElementById('qr-timeout-slide');
    let timeoutId;

    // If redirected with a success param, jump to the last slide
    if (urlParams.has('thank_you') && surveyCarouselEl) {
        history.replaceState({}, document.title, window.location.pathname);

        // Instant jump without transition animation
        document.querySelector('.carousel-inner').style.transition = 'none';
        surveyCarousel.to(QR_SLIDE_INDEX);

        setTimeout(() => {
             document.querySelector('.carousel-inner').style.transition = '';
        }, 50);
    }

    // Auto-reset the form if left on the QR slide too long
    if (surveyCarouselEl && qrSlide) {
        surveyCarouselEl.addEventListener('slid.bs.carousel', function () {
            if (qrSlide.classList.contains('active')) {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => {
                    window.location.reload();
                }, 1 * 60 * 1000); // 1 minute
            } else {
                clearTimeout(timeoutId);
            }
        });
    }

    // PWA Setup
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw-survey.js', {
                scope: '/survey'
            }).catch(function (error) {
                console.error('Service worker registration failed:', error);
            });
        });
    }

    let deferredPrompt = null;
    const installBtn = document.getElementById('installSurveyApp');

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;
        if (installBtn) installBtn.style.display = 'inline-block';
    });

    installBtn?.addEventListener('click', async function () {
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        await deferredPrompt.userChoice;
        deferredPrompt = null;
        installBtn.style.display = 'none';
    });
});
