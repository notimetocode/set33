{{--
  Баннер согласия на cookie. Показывается, пока нет сохранённого выбора.
  Скрывается до гидрации JS, если согласие уже записано (localStorage).
--}}
<div
    class="cookie-consent"
    data-cookie-consent
    role="dialog"
    aria-labelledby="cookie-consent-title"
    aria-describedby="cookie-consent-text"
    hidden
>
    <div class="cookie-consent__inner">
        <div class="cookie-consent__content">
            <span class="cookie-consent__icon" aria-hidden="true" data-cookie-consent-icon></span>

            <div class="cookie-consent__copy">
                <p class="cookie-consent__title" id="cookie-consent-title">Мы используем cookie</p>
                <p class="cookie-consent__text" id="cookie-consent-text">
                    Мы используем файлы cookie для улучшения вашего опыта просмотра, анализа трафика сайта
                    и персонализации контента. Нажимая «Принять все», вы соглашаетесь на использование нами
                    файлов cookie, включая аналитику.
                </p>
            </div>
        </div>

        <div class="cookie-consent__actions">
            <button
                type="button"
                class="cookie-consent__btn cookie-consent__btn--decline"
                data-cookie-consent-decline
            >
                <span class="cookie-consent__btn-label">Отклонить все</span>
                <span class="cookie-consent__btn-hint">кроме необходимых</span>
            </button>

            <button
                type="button"
                class="cookie-consent__btn cookie-consent__btn--accept"
                data-cookie-consent-accept
            >
                Принять все
            </button>
        </div>
    </div>
</div>
