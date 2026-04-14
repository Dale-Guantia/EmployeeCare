<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0056b3">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="HRDO Survey">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192x192.png') }}">
    <link rel="stylesheet" href="{{ asset('arta-assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('arta-assets/css/fontawesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('arta-assets/css/css2.css') }}">
    <link rel="stylesheet" href="{{ asset('arta-assets/css/arta.css') }}">
    <link rel="manifest" href="{{ asset('manifest-survey.json') }}">
    <title>ARTA Client Satisfaction Form</title>
</head>
<body>
    <button id="installSurveyApp" type="button" style="display:none;">Install App</button>

    <video id="background-video" autoplay loop muted playsinline preload="auto">
        <source src="{{ asset('storage/assets/blue.webm') }}" type="video/webm">
        <source src="{{ asset('storage/assets/blue.mp4') }}" type="video/mp4">
        <img src="{{ asset('storage/assets/blue.webp') }}" alt="Background">
        <img src="{{ asset('storage/assets/blue.jpg') }}" alt="Background">
        Your browser does not support the video tag.
    </video>

    <div class="survey-shell">
        <header class="survey-header" id="surveyHeader">
        </header>

        <form action="{{ route('survey.submit') }}" method="POST" class="survey-form">
            @csrf

            <div id="surveyCarousel" class="carousel slide" data-bs-ride="false" data-bs-interval="false">
                <div class="carousel-inner">

                    {{-- Slide 1: Front--}}
                    <div class="carousel-item active">
                        <div class="question-slide question-slide--division">
                            {{-- <h3 class="slide-title">Select Division / Pumili ng Dibisyon:</h3> --}}

                            <div class="center-logo-container">
                                <picture>
                                    <source type="image/webp" srcset="{{ asset('storage/assets/logo-with-seal.webp') }}">
                                    <img src="{{ asset('storage/assets/logo-with-seal.webp') }}" alt="HRDO Logo" loading="lazy">
                                </picture>
                            </div>

                            <h3 class="survey-title">HELP US SERVE YOU BETTER!</h3>
                            <div class="directions">
                                <h4>This Client Satisfaction Measurement (CSM) tracks the customer experience of government offices. Your feedback on your <u>recently concluded transaction</u> will help this office provide a better service. Personal information shared will be kept confidential and you always have the option not to answer this form.</h4>
                            </div>
                            <button type="button" class="btn btn-primary go-back-btn-large" onclick="nextSlide()">Start</button>
                        </div>
                    </div>

                    {{-- Slide 2: Client Type --}}
                    <div class="carousel-item" id="staff-selection-slide">
                        <div class="question-slide">
                            <h3 class="slide-title">Client Type:</h3>
                            <div>
                                <input type="checkbox" id="" name="" value=""><label> Citizen</label><br>
                                <input type="checkbox" id="" name="" value=""><label> Business</label><br>
                                <input type="checkbox" id="" name="" value=""><label> Government (Employee or another agency)</label><br>
                            </div>

                            <div>
                                <button type="button" class="btn btn-primary go-back-btn-large" onclick="prevSlide()">Go Back</button>
                                <button type="button" class="btn btn-primary go-back-btn-large" onclick="nextSlide()">Next</button>
                            </div>
                        </div>
                    </div>

                    {{-- Slide 3: Sex --}}
                    <div class="carousel-item service-slide">
                        <div class="question-slide">
                            <h3 class="slide-title">Sex: </h3>

                            <div>
                                <input type="checkbox" id="" name="" value=""><label> Male</label><br>
                                <input type="checkbox" id="" name="" value=""><label> Female</label><br>
                            </div>

                            <div>
                                <button type="button" class="btn btn-primary go-back-btn-large" onclick="prevSlide()">Go Back</button>
                                <button type="button" class="btn btn-primary go-back-btn-large" onclick="nextSlide()">Next</button>
                            </div>
                        </div>
                    </div>

                    {{-- Slide 4: Age --}}
                    <div class="carousel-item service-slide">
                        <div class="question-slide">
                            <h3 class="slide-title">Age: </h3>

                            <div>
                                <input type="number" id="age" placeholder="Enter Age" autofocus style="border: solid 1px">
                            </div>

                            <div>
                                <button type="button" class="btn btn-primary go-back-btn-large" onclick="prevSlide()">Go Back</button>
                                <button type="button" class="btn btn-primary go-back-btn-large" onclick="nextSlide()">Next</button>
                            </div>
                        </div>
                    </div>

                    {{-- Slide 5: Region --}}
                    <div class="carousel-item service-slide">
                        <div class="question-slide">
                            <h3 class="slide-title">Region of residence: </h3>

                            <div>
                                <input type="text" style="border: solid 1px">
                            </div>

                            <div>
                                <button type="button" class="btn btn-primary go-back-btn-large" onclick="prevSlide()">Go Back</button>
                                <button type="button" class="btn btn-primary go-back-btn-large" onclick="nextSlide()">Next</button>
                            </div>
                        </div>
                    </div>

                    {{-- Slide 6: Service --}}
                    <div class="carousel-item service-slide">
                        <div class="question-slide">
                            <h3 class="slide-title">Service Availed: </h3>

                            <div>
                                <input type="text" style="border: solid 1px">
                            </div>

                            <div>
                                <button type="button" class="btn btn-primary go-back-btn-large" onclick="prevSlide()">Go Back</button>
                                <button type="button" class="btn btn-primary go-back-btn-large" onclick="nextSlide()">Next</button>
                            </div>
                        </div>
                    </div>

                    {{-- Slide 7: CC1 --}}
                    <div class="carousel-item service-slide">
                        <div class="question-slide">
                            <div class="instructions">
                                <p>INSTRUCTIONS: Please place a Check mark (✔) in the designated box that corresponds to your answer on
                                the Citizen’s Charter (CC) questions. The Citizen’s Charter is an official document that reflects the services of a
                                government agency/office including its requirements, fees, and processing times among others.</p>
                            </div>

                            <h3 class="slide-title">CC1: Which of the following best describes your awareness of a CC?</h3>

                            <div>
                                <input type="checkbox" id="" name="" value=""><label> 1. I know what a CC is and I saw this office’s CC.</label><br>
                                <input type="checkbox" id="" name="" value=""><label> 2. I know what a CC is but I did NOT see this office’s CC.</label><br>
                                <input type="checkbox" id="" name="" value=""><label> 3. I learned of the CC only when I saw this office’s CC.</label><br>
                                <input type="checkbox" id="" name="" value=""><label> 4. I do not know what a CC is and I did not see one in this office. (Answer ‘N/A’ on CC2 and CC3)</label><br>
                            </div>

                            <div>
                                <button type="button" class="btn btn-primary go-back-btn-large" onclick="prevSlide()">Go Back</button>
                                <button type="button" class="btn btn-primary go-back-btn-large" onclick="nextSlide()">Next</button>
                            </div>
                        </div>
                    </div>

                    {{-- Slide 8: CC2 --}}
                    <div class="carousel-item service-slide">
                        <div class="question-slide">
                            <div class="instructions">
                                <p>INSTRUCTIONS: Please place a Check mark (✔) in the designated box that corresponds to your answer on
                                the Citizen’s Charter (CC) questions. The Citizen’s Charter is an official document that reflects the services of a
                                government agency/office including its requirements, fees, and processing times among others.</p>
                            </div>

                            <h3 class="slide-title">CC2: If aware of CC (answered 1-3 in CC1), would you say that the CC of this office was ...?</h3>

                            <div>
                                <input type="checkbox" id="" name="" value=""><label> 1. Easy to see</label><br>
                                <input type="checkbox" id="" name="" value=""><label> 2. Somewhat easy to see</label><br>
                                <input type="checkbox" id="" name="" value=""><label> 3. Difficult to see</label><br>
                                <input type="checkbox" id="" name="" value=""><label> 4. Not visible at all</label><br>
                                <input type="checkbox" id="" name="" value=""><label> 5. Not Applicable</label><br>
                            </div>

                            <div>
                                <button type="button" class="btn btn-primary go-back-btn-large" onclick="prevSlide()">Go Back</button>
                                <button type="button" class="btn btn-primary go-back-btn-large" onclick="nextSlide()">Next</button>
                            </div>
                        </div>
                    </div>

                    {{-- Slide 9: CC3 --}}
                    <div class="carousel-item service-slide">
                        <div class="question-slide">
                            <div class="instructions">
                                <p>INSTRUCTIONS: Please place a Check mark (✔) in the designated box that corresponds to your answer on
                                the Citizen’s Charter (CC) questions. The Citizen’s Charter is an official document that reflects the services of a
                                government agency/office including its requirements, fees, and processing times among others.</p>
                            </div>

                            <h3 class="slide-title">CC3: If aware of CC (answered codes 1-3 in CC1), how much did the CC help you in your transaction?</h3>

                            <div>
                                <input type="checkbox" id="" name="" value=""><label> 1. Helped very much</label><br>
                                <input type="checkbox" id="" name="" value=""><label> 2. Somewhat helped</label><br>
                                <input type="checkbox" id="" name="" value=""><label> 3. Did not help</label><br>
                                <input type="checkbox" id="" name="" value=""><label> 4. Not Applicable</label><br>
                            </div>

                            <div>
                                <button type="button" class="btn btn-primary go-back-btn-large" onclick="prevSlide()">Go Back</button>
                                <button type="button" class="btn btn-primary go-back-btn-large" onclick="nextSlide()">Next</button>
                            </div>
                        </div>
                    </div>

                    {{-- Slide 10: SQD0 --}}
                    <div class="carousel-item">
                        <div class="question-slide question-slide--rating">
                            <div class="instructions">
                                <p>INSTRUCTIONS: For SQD 0-8, please tap on the emoji that best corresponds to your answer.</p>
                            </div>
                            <h2 class="question-heading">SQD0: I am satisfied with the service that I availed.</h2>

                            <div class="rating-row">
                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Dissatisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Dissatisfied" class="visually-hidden" required>
                                    <span class="emoji-icon">😔</span>
                                    <span class="emoji-rating-text">Strongly Dissagree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Dissatisfied')">
                                    <input type="radio" name="timeliness_rating" value="Dissatisfied" class="visually-hidden">
                                    <span class="emoji-icon">☹️</span>
                                    <span class="emoji-rating-text">Dissagree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">😐</span>
                                    <span class="emoji-rating-text">Neither Agree nor Disagree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">🙂</span>
                                    <span class="emoji-rating-text">Agree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">😁</span>
                                    <span class="emoji-rating-text">Strongly Agree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">N/A</span>
                                    <span class="emoji-rating-text">Not Applicable</span>
                                </label>
                            </div>

                            <button type="button" class="btn btn-primary go-back-btn-large" onclick="prevSlide()">Go Back</button>
                        </div>
                    </div>

                    {{-- Slide 11: SQD1 --}}
                    <div class="carousel-item">
                        <div class="question-slide question-slide--rating">
                            <div class="instructions">
                                <p>INSTRUCTIONS: For SQD 0-8, please tap on the emoji that best corresponds to your answer.</p>
                            </div>
                            <h2 class="question-heading">SQD1: I spent a reasonable amount of time for my transaction.</h2>

                            <div class="rating-row">
                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Dissatisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Dissatisfied" class="visually-hidden" required>
                                    <span class="emoji-icon">😔</span>
                                    <span class="emoji-rating-text">Strongly Dissagree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Dissatisfied')">
                                    <input type="radio" name="timeliness_rating" value="Dissatisfied" class="visually-hidden">
                                    <span class="emoji-icon">☹️</span>
                                    <span class="emoji-rating-text">Dissagree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">😐</span>
                                    <span class="emoji-rating-text">Neither Agree nor Disagree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">🙂</span>
                                    <span class="emoji-rating-text">Agree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">😁</span>
                                    <span class="emoji-rating-text">Strongly Agree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">N/A</span>
                                    <span class="emoji-rating-text">Not Applicable</span>
                                </label>
                            </div>

                            <button type="button" class="btn btn-primary go-back-btn-large" onclick="prevSlide()">Go Back</button>
                        </div>
                    </div>

                    {{-- Slide 12: SQD2 --}}
                    <div class="carousel-item">
                        <div class="question-slide question-slide--rating">
                            <div class="instructions">
                                <p>INSTRUCTIONS: For SQD 0-8, please tap on the emoji that best corresponds to your answer.</p>
                            </div>
                            <h2 class="question-heading">SQD2: The office followed the transaction’s requirements and steps based on the information provided.</h2>

                            <div class="rating-row">
                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Dissatisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Dissatisfied" class="visually-hidden" required>
                                    <span class="emoji-icon">😔</span>
                                    <span class="emoji-rating-text">Strongly Dissagree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Dissatisfied')">
                                    <input type="radio" name="timeliness_rating" value="Dissatisfied" class="visually-hidden">
                                    <span class="emoji-icon">☹️</span>
                                    <span class="emoji-rating-text">Dissagree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">😐</span>
                                    <span class="emoji-rating-text">Neither Agree nor Disagree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">🙂</span>
                                    <span class="emoji-rating-text">Agree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">😁</span>
                                    <span class="emoji-rating-text">Strongly Agree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">N/A</span>
                                    <span class="emoji-rating-text">Not Applicable</span>
                                </label>
                            </div>

                            <button type="button" class="btn btn-primary go-back-btn-large" onclick="prevSlide()">Go Back</button>
                        </div>
                    </div>

                    {{-- Slide 13: SQD3 --}}
                    <div class="carousel-item">
                        <div class="question-slide question-slide--rating">
                            <div class="instructions">
                                <p>INSTRUCTIONS: For SQD 0-8, please tap on the emoji that best corresponds to your answer.</p>
                            </div>
                            <h2 class="question-heading">SQD3: The steps (including payment) I needed to do for my transaction were easy and simple.</h2>

                            <div class="rating-row">
                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Dissatisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Dissatisfied" class="visually-hidden" required>
                                    <span class="emoji-icon">😔</span>
                                    <span class="emoji-rating-text">Strongly Dissagree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Dissatisfied')">
                                    <input type="radio" name="timeliness_rating" value="Dissatisfied" class="visually-hidden">
                                    <span class="emoji-icon">☹️</span>
                                    <span class="emoji-rating-text">Dissagree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">😐</span>
                                    <span class="emoji-rating-text">Neither Agree nor Disagree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">🙂</span>
                                    <span class="emoji-rating-text">Agree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">😁</span>
                                    <span class="emoji-rating-text">Strongly Agree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">N/A</span>
                                    <span class="emoji-rating-text">Not Applicable</span>
                                </label>
                            </div>

                            <button type="button" class="btn btn-primary go-back-btn-large" onclick="prevSlide()">Go Back</button>
                        </div>
                    </div>

                    {{-- Slide 14: SQD4 --}}
                    <div class="carousel-item">
                        <div class="question-slide question-slide--rating">
                            <div class="instructions">
                                <p>INSTRUCTIONS: For SQD 0-8, please tap on the emoji that best corresponds to your answer.</p>
                            </div>
                            <h2 class="question-heading">SQD4: I easily found information about my transaction from the office or its website.</h2>

                            <div class="rating-row">
                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Dissatisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Dissatisfied" class="visually-hidden" required>
                                    <span class="emoji-icon">😔</span>
                                    <span class="emoji-rating-text">Strongly Dissagree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Dissatisfied')">
                                    <input type="radio" name="timeliness_rating" value="Dissatisfied" class="visually-hidden">
                                    <span class="emoji-icon">☹️</span>
                                    <span class="emoji-rating-text">Dissagree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">😐</span>
                                    <span class="emoji-rating-text">Neither Agree nor Disagree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">🙂</span>
                                    <span class="emoji-rating-text">Agree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">😁</span>
                                    <span class="emoji-rating-text">Strongly Agree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">N/A</span>
                                    <span class="emoji-rating-text">Not Applicable</span>
                                </label>
                            </div>

                            <button type="button" class="btn btn-primary go-back-btn-large" onclick="prevSlide()">Go Back</button>
                        </div>
                    </div>

                    {{-- Slide 15: SQD5 --}}
                      <div class="carousel-item">
                        <div class="question-slide question-slide--rating">
                            <div class="instructions">
                                <p>INSTRUCTIONS: For SQD 0-8, please tap on the emoji that best corresponds to your answer.</p>
                            </div>
                            <h2 class="question-heading">SQD5: I paid a reasonable amount of fees for my transaction. (If service was free, mark the ‘N/A’ column)</h2>

                            <div class="rating-row">
                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Dissatisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Dissatisfied" class="visually-hidden" required>
                                    <span class="emoji-icon">😔</span>
                                    <span class="emoji-rating-text">Strongly Dissagree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Dissatisfied')">
                                    <input type="radio" name="timeliness_rating" value="Dissatisfied" class="visually-hidden">
                                    <span class="emoji-icon">☹️</span>
                                    <span class="emoji-rating-text">Dissagree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">😐</span>
                                    <span class="emoji-rating-text">Neither Agree nor Disagree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">🙂</span>
                                    <span class="emoji-rating-text">Agree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">😁</span>
                                    <span class="emoji-rating-text">Strongly Agree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">N/A</span>
                                    <span class="emoji-rating-text">Not Applicable</span>
                                </label>
                            </div>

                            <button type="button" class="btn btn-primary go-back-btn-large" onclick="prevSlide()">Go Back</button>
                        </div>
                    </div>

                    {{-- Slide 16: SQD6 --}}
                    <div class="carousel-item">
                        <div class="question-slide question-slide--rating">
                            <div class="instructions">
                                <p>INSTRUCTIONS: For SQD 0-8, please tap on the emoji that best corresponds to your answer.</p>
                            </div>
                            <h2 class="question-heading">SQD6: I feel the office was fair to everyone, or “walang palakasan”, during my transaction.</h2>

                            <div class="rating-row">
                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Dissatisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Dissatisfied" class="visually-hidden" required>
                                    <span class="emoji-icon">😔</span>
                                    <span class="emoji-rating-text">Strongly Dissagree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Dissatisfied')">
                                    <input type="radio" name="timeliness_rating" value="Dissatisfied" class="visually-hidden">
                                    <span class="emoji-icon">☹️</span>
                                    <span class="emoji-rating-text">Dissagree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">😐</span>
                                    <span class="emoji-rating-text">Neither Agree nor Disagree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">🙂</span>
                                    <span class="emoji-rating-text">Agree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">😁</span>
                                    <span class="emoji-rating-text">Strongly Agree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">N/A</span>
                                    <span class="emoji-rating-text">Not Applicable</span>
                                </label>
                            </div>

                            <button type="button" class="btn btn-primary go-back-btn-large" onclick="prevSlide()">Go Back</button>
                        </div>
                    </div>

                    {{-- Slide 17: SQD7 --}}
                    <div class="carousel-item">
                        <div class="question-slide question-slide--rating">
                            <div class="instructions">
                                <p>INSTRUCTIONS: For SQD 0-8, please tap on the emoji that best corresponds to your answer.</p>
                            </div>
                            <h2 class="question-heading">SQD7: I was treated courteously by the staff, and (if asked for help) the staff was helpful.</h2>

                            <div class="rating-row">
                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Dissatisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Dissatisfied" class="visually-hidden" required>
                                    <span class="emoji-icon">😔</span>
                                    <span class="emoji-rating-text">Strongly Dissagree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Dissatisfied')">
                                    <input type="radio" name="timeliness_rating" value="Dissatisfied" class="visually-hidden">
                                    <span class="emoji-icon">☹️</span>
                                    <span class="emoji-rating-text">Dissagree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">😐</span>
                                    <span class="emoji-rating-text">Neither Agree nor Disagree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">🙂</span>
                                    <span class="emoji-rating-text">Agree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">😁</span>
                                    <span class="emoji-rating-text">Strongly Agree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">N/A</span>
                                    <span class="emoji-rating-text">Not Applicable</span>
                                </label>
                            </div>

                            <button type="button" class="btn btn-primary go-back-btn-large" onclick="prevSlide()">Go Back</button>
                        </div>
                    </div>

                    {{-- Slide 18: SQD8 --}}
                    <div class="carousel-item">
                        <div class="question-slide question-slide--rating">
                            <div class="instructions">
                                <p>INSTRUCTIONS: For SQD 0-8, please tap on the emoji that best corresponds to your answer.</p>
                            </div>
                            <h2 class="question-heading">SQD8. I got what I needed from the government office, or (if denied) denial of request was sufficiently explained to me.</h2>

                            <div class="rating-row">
                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Dissatisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Dissatisfied" class="visually-hidden" required>
                                    <span class="emoji-icon">😔</span>
                                    <span class="emoji-rating-text">Strongly Dissagree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Dissatisfied')">
                                    <input type="radio" name="timeliness_rating" value="Dissatisfied" class="visually-hidden">
                                    <span class="emoji-icon">☹️</span>
                                    <span class="emoji-rating-text">Dissagree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">😐</span>
                                    <span class="emoji-rating-text">Neither Agree nor Disagree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">🙂</span>
                                    <span class="emoji-rating-text">Agree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">😁</span>
                                    <span class="emoji-rating-text">Strongly Agree</span>
                                </label>

                                <label class="rating-option" onclick="selectRating(this, 'timeliness_rating', 'Very Satisfied')">
                                    <input type="radio" name="timeliness_rating" value="Very Satisfied" class="visually-hidden">
                                    <span class="emoji-icon">N/A</span>
                                    <span class="emoji-rating-text">Not Applicable</span>
                                </label>
                            </div>

                            <button type="button" class="btn btn-primary go-back-btn-large" onclick="prevSlide()">Go Back</button>
                        </div>
                    </div>

                    {{-- Slide : QR --}}
                    <div class="carousel-item" id="qr-timeout-slide">
                        <div class="question-slide question-slide--qr">
                            @if(session('success'))
                                <div id="success-message" class="alert alert-success success-message" role="alert">
                                    {{ session('success') }}
                                </div>
                            @endif

                            <h3 class="slide-title slide-title--blue">Scan the QR code to fill out the comments and suggestions form</h3>

                            <div class="qr-code-container">
                                {!! QrCode::backgroundColor(255, 255, 255, 0)->generate('https://forms.gle/Tvmm2WmjHGNqteUD9') !!}
                            </div>

                            <button type="button" class="btn btn-primary go-back-btn-large" onclick="window.location.reload()">Rate Again</button>
                        </div>
                    </div>

                </div>
            </div>
        </form>

        <div class="carousel-indicators">
            <button type="button" data-bs-target="#surveyCarousel" class="active" aria-current="true" aria-label="Slide 1"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 2"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 3"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 4"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 5"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 6"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 7"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 8"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 9"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 10"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 11"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 12"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 13"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 14"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 15"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 16"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 17"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 18"></button>
            <button type="button" data-bs-target="#surveyCarousel" aria-label="Slide 19"></button>
        </div>
    </div>

    <script src="{{ asset('arta-assets/js/jquery-3.6.0.min.js') }}"></script>
    <script src="{{ asset('arta-assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('arta-assets/js/browser@4.js') }}"></script>
    <script src="{{ asset('arta-assets/js/arta.js') }}"></script>
</body>
</html>
