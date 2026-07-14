<div class="app-modal" id="app-modal" aria-hidden="true">
  <div class="app-modal__backdrop" data-modal-close></div>

  <div class="app-modal__panel" role="dialog" aria-modal="true" aria-labelledby="app-modal-title">
    <button type="button" class="app-modal__close" data-modal-close aria-label="Close">
      &times;
    </button>

    <div class="app-modal__icon" id="app-modal-icon" style="display:none;"></div>

    <h2 id="app-modal-title"></h2>

    <p class="app-modal__lead" id="app-modal-lead" style="display:none;"></p>

    <div class="app-modal__details" id="app-modal-details" style="display:none;"></div>

    <p class="app-modal__warning" id="app-modal-warning" style="display:none;"></p>

    <p class="app-modal__note" id="app-modal-note" style="display:none;"></p>

    <div class="app-modal__error" id="app-modal-error" style="display:none;"></div>

    <form method="post" id="app-modal-form">
      <div id="app-modal-hidden-fields"></div>

      <div class="app-modal__actions">
        <button type="button" class="btn-two" data-modal-close>
          Cancel
        </button>

        <button type="submit" class="btn-three" id="app-modal-confirm">
          Confirm
        </button>
      </div>
    </form>
  </div>
</div>