const surveyCarouselEl = document.getElementById('surveyCarousel');
const surveyCarousel = new bootstrap.Carousel(surveyCarouselEl, {
    touch: false,
    interval: false,
    wrap: false
});

// function showViewportDebug() {
//     const oldDebug = document.getElementById('viewport-debug');
//     if (oldDebug) oldDebug.remove();

//     const debug = document.createElement('div');
//     debug.id = 'viewport-debug';
//     debug.style.position = 'fixed';
//     debug.style.left = '10px';
//     debug.style.bottom = '10px';
//     debug.style.zIndex = '99999';
//     debug.style.background = 'rgba(0, 0, 0, 0.75)';
//     debug.style.color = '#fff';
//     debug.style.padding = '8px 10px';
//     debug.style.borderRadius = '8px';
//     debug.style.fontSize = '12px';
//     debug.style.lineHeight = '1.4';
//     debug.style.fontFamily = 'Arial, sans-serif';
//     debug.style.whiteSpace = 'pre-line';

//     debug.textContent =
//         'innerWidth: ' + window.innerWidth + '\n' +
//         'innerHeight: ' + window.innerHeight + '\n' +
//         'devicePixelRatio: ' + window.devicePixelRatio + '\n' +
//         'screen.width: ' + screen.width + '\n' +
//         'screen.height: ' + screen.height;

//     document.body.appendChild(debug);
// }

// window.addEventListener('load', showViewportDebug);
// window.addEventListener('resize', showViewportDebug);
// window.addEventListener('orientationchange', showViewportDebug);

let servicePage = 0;
let staffPage = 0;
let isSubmittingSurvey = false;

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

    if (width <= 640) return 1;
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

function submitForm() {
    if (isSubmittingSurvey) return;

    isSubmittingSurvey = true;

    document.querySelectorAll('.rating-option').forEach(option => {
        option.style.pointerEvents = 'none';
    });

    document.querySelector('form').submit();
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

    const row = selectedLabel.closest('.rating-row');
    const labels = row.querySelectorAll('.rating-option');

    labels.forEach(label => label.classList.remove('active'));
    selectedLabel.classList.add('active');

    const input = selectedLabel.querySelector(`input[name="${inputName}"]`);
    if (input) input.checked = true;

    if (isLastQuestion) {
        setTimeout(() => {
            submitForm();
        }, 250);
        return;
    }

    setTimeout(() => {
        nextSlide();
    }, 200);
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
        e.preventDefault();

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
    });

    $(window).on('resize orientationchange', function () {
        handleResize();
    });

    setTimeout(() => {
        $('#success-message').fadeOut('slow');
    }, 5000);
});

document.addEventListener('DOMContentLoaded', function () {
    const QR_SLIDE_INDEX = 7;
    const urlParams = new URLSearchParams(window.location.search);
    const qrSlide = document.getElementById('qr-timeout-slide');
    let timeoutId;

    if (urlParams.has('thank_you') && surveyCarouselEl) {
        history.replaceState({}, document.title, window.location.pathname);
        surveyCarousel.to(QR_SLIDE_INDEX);
    }

    if (surveyCarouselEl && qrSlide) {
        surveyCarouselEl.addEventListener('slid.bs.carousel', function () {
            if (qrSlide.classList.contains('active')) {
                clearTimeout(timeoutId);

                timeoutId = setTimeout(() => {
                    window.location.reload();
                }, 3 * 60 * 1000);
            } else {
                clearTimeout(timeoutId);
            }
        });
    }

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

        if (installBtn) {
            installBtn.style.display = 'inline-block';
        }
    });

    installBtn?.addEventListener('click', async function () {
        if (!deferredPrompt) return;

        deferredPrompt.prompt();
        await deferredPrompt.userChoice;
        deferredPrompt = null;
        installBtn.style.display = 'none';
    });
});
