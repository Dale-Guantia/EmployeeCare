@extends(backpack_view('blank'))

@section('content')
<div class="submit-ticket-page">
            <section class="submit-ticket-hero" aria-labelledby="submit-ticket-heading">
                <div class="submit-ticket-hero__inner">
                    <div class="submit-ticket-hero__icon" aria-hidden="true">
                        <i class="la la-life-ring"></i>
                    </div>

                    <h1 id="submit-ticket-heading" class="submit-ticket-hero__title">Need help with something?</h1>
                    <p class="submit-ticket-hero__subtitle">
                        Submit a ticket and the right HR team will get back to you.
                    </p>

                    <a href="{{ $ticketCreateUrl }}" class="btn btn-primary btn-lg submit-ticket-hero__cta">
                        <i class="la la-paper-plane" aria-hidden="true"></i> Submit Ticket
                    </a>
                </div>
            </section>
</div>

@endsection

@push('after_styles')
<style>
    .app-body:has(.submit-ticket-page) {
        display: flex;
        padding: 0 !important;
        background: linear-gradient(135deg, #edf3ff 0%, #fff 70%);
    }

    .main:has(.submit-ticket-page) {
        display: flex;
        flex: 1;
        flex-direction: column;
        min-width: 0;
        padding-top: 0 !important;
    }

    .main:has(.submit-ticket-page) > .container-fluid {
        display: flex;
        flex: 1;
        padding: 0;
    }

    .submit-ticket-page {
        display: flex;
        flex: 1;
        width: 100%;
    }

    .submit-ticket-hero {
        min-height: 320px;
        flex: 1;
        margin: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: clamp(1.5rem, 4vw, 4rem);
    }

    .submit-ticket-hero__inner {
        max-width: 600px;
        width: 100%;
    }

    .submit-ticket-hero__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 4rem;
        height: 4rem;
        font-size: 2.25rem;
        color: #375ca6;
        background: #e2ebfb;
        border-radius: 1rem;
        margin-bottom: 1.25rem;
    }

    .submit-ticket-hero__title {
        font-size: clamp(1.6rem, 2.5vw, 2.25rem);
        font-weight: 700;
        color: #1b2a4e;
        margin-bottom: 0.75rem;
    }

    .submit-ticket-hero__subtitle {
        font-size: 1.05rem;
        color: #53647d;
        line-height: 1.6;
        margin-bottom: 2rem;
    }

    .submit-ticket-hero__cta {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        min-height: 48px;
        padding: 0.85rem 2.25rem;
        font-size: 1.05rem;
        font-weight: 600;
        border-radius: 0.65rem;
        box-shadow: 0 4px 12px rgba(55, 92, 166, 0.18);
        transition: background-color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .submit-ticket-hero__cta:hover {
        background-color: var(--ec-primary-hover, #2f4e8d);
        border-color: var(--ec-primary-hover, #2f4e8d);
        color: #fff;
        box-shadow: 0 6px 16px rgba(55, 92, 166, 0.24);
    }

    .submit-ticket-hero__cta:focus-visible {
        outline: 3px solid #375ca6;
        outline-offset: 4px;
    }

    .submit-ticket-hero__cta:active {
        background-color: var(--ec-primary-active, #2c4a85);
        border-color: var(--ec-primary-active, #2c4a85);
        color: #fff;
        box-shadow: none;
    }

    @media (max-width: 576px) {
        .submit-ticket-hero {
            min-height: 320px;
            padding: 1.25rem;
        }

        .submit-ticket-hero__title {
            font-size: 1.6rem;
        }

        .submit-ticket-hero__subtitle {
            font-size: 0.95rem;
        }

        .submit-ticket-hero__cta {
            width: 100%;
            padding: 0.85rem 1.5rem;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .submit-ticket-hero__cta { transition: none; }
    }
</style>
@endpush
