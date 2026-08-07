(() => {
  'use strict';
  let stream = null;
  let reconnectTimer = null;
  let activeDraftForm = null;
  let activeDraftType = '';
  const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, (character) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[character]));
  const clipboardPng = async (blob) => {
    if (blob.type === 'image/png') return blob;
    const image = new Image();
    const source = URL.createObjectURL(blob);
    try {
      await new Promise((resolve, reject) => { image.onload = resolve; image.onerror = reject; image.src = source; });
      const canvas = document.createElement('canvas'); canvas.width = image.naturalWidth; canvas.height = image.naturalHeight;
      canvas.getContext('2d').drawImage(image, 0, 0);
      const png = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
      if (!png) throw new Error('Foto konnte nicht für die Zwischenablage aufbereitet werden.');
      return png;
    } finally { URL.revokeObjectURL(source); }
  };

  const refresh = (root) => {
    if (!root || !window.htmx) return;
    htmx.ajax('GET', root.dataset.inboxUrl, {target: '#companion-inbox', swap: 'outerHTML'});
  };
  const consume = (root, itemId, target) => {
    if (!root || !itemId || !window.htmx) return;
    htmx.ajax('POST', `${root.dataset.inboxUrl}/${itemId}/uebernehmen`, {
      target: '#companion-inbox', swap: 'outerHTML', values: {target}
    });
  };
  const closePopovers = (except = null) => document.querySelectorAll('[data-companion-for]').forEach((button) => { if (button !== except) window.bootstrap?.Popover.getInstance(button)?.dispose(); });
  const closeDraftPhotoPopovers = () => document.querySelectorAll('[data-companion-draft-photo]').forEach((button) => window.bootstrap?.Popover.getInstance(button)?.dispose());
  const applyValue = (input, value) => {
    if (input.tomselect) input.tomselect.setValue(value, true);
    else { input.value = value; input.dispatchEvent(new Event('input', {bubbles: true})); input.dispatchEvent(new Event('change', {bubbles: true})); }
    input.focus();
  };
  const bindInputs = (root) => {
    document.querySelectorAll('[data-companion-for]').forEach((button) => {
      if (root.dataset.hasActiveConnection !== '1') { button.classList.add('d-none'); return; }
      button.classList.remove('d-none');
      if (button.dataset.companionBound) return;
      button.dataset.companionBound = '1';
      button.addEventListener('click', (event) => {
        event.preventDefault();
        const input = document.getElementById(button.dataset.companionFor);
        if (!input) return;
        const old = window.bootstrap?.Popover.getInstance(button);
        // A field button is a toggle: the second click closes its own menu.
        if (old && button.getAttribute('aria-describedby')) { old.dispose(); return; }
        if (old) old.dispose();
        closePopovers(button);
        const content = root.dataset.hasActiveConnection === '1'
          ? `<div hx-get="${root.dataset.inboxUrl}?field=${encodeURIComponent(button.dataset.companionFor)}" hx-trigger="load" hx-swap="innerHTML"><span class="small"><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Companion-Werte laden …</span></div>`
          : '<span class="small">Noch kein Smartphone verbunden. Im Profil unter „Companion-Geräte“ einen QR-Code erzeugen und auf dem Handy öffnen.</span>';
        const popover = new window.bootstrap.Popover(button, {html: true, sanitize: false, trigger: 'manual', placement: 'bottom', content});
        popover.show();
        window.htmx?.process(document.getElementById(button.getAttribute('aria-describedby') || ''));
      });
      // Desktop users can inspect incoming values without an extra click. Touch
      // devices keep the ordinary click interaction.
      if (window.matchMedia && window.matchMedia('(hover: hover)').matches) {
        let closeTimer = null;
        const cancelClose = () => { if (closeTimer) { window.clearTimeout(closeTimer); closeTimer = null; } };
        const scheduleClose = () => {
          cancelClose();
          closeTimer = window.setTimeout(() => window.bootstrap?.Popover.getInstance(button)?.dispose(), 260);
        };
        button.addEventListener('mouseenter', () => {
          cancelClose();
          if (!window.bootstrap?.Popover.getInstance(button)?.tip) button.click();
          const tipId = button.getAttribute('aria-describedby');
          const tip = tipId ? document.getElementById(tipId) : null;
          if (tip && !tip.dataset.companionHoverBound) {
            tip.dataset.companionHoverBound = '1';
            tip.addEventListener('mouseenter', cancelClose);
            tip.addEventListener('mouseleave', scheduleClose);
          }
        });
        button.addEventListener('mouseleave', scheduleClose);
      }
    });
  };
  const openDraftPhotoChoices = (button, form, root) => {
    if (!root) return;
    activeDraftForm = form;
    activeDraftType = button.closest('.btn-group')?.querySelector('[data-open-typeplate]') ? 'type_plate' : '';
    const old = window.bootstrap?.Popover.getInstance(button);
    if (old) old.dispose();
    const content = `<div hx-get="${root.dataset.inboxUrl}?kind=photo" hx-trigger="load" hx-swap="innerHTML"><span class="small"><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Companion-Fotos laden …</span></div>`;
    const popover = new window.bootstrap.Popover(button, {html: true, sanitize: false, trigger: 'manual', placement: 'bottom', content});
    popover.show();
    window.htmx?.process(document.getElementById(button.getAttribute('aria-describedby') || ''));
  };
  const bindDraftPhotoSelectors = (root) => {
    document.querySelectorAll('[data-stage-device-photo]').forEach((photo) => {
      const form = photo.closest('form.device-form');
      if (!form || photo.dataset.companionPhotoBound) return;
      photo.dataset.companionPhotoBound = '1';
      const type = form.querySelector('[name="new_device_photo_type"]');
      if (type && type.value === 'type_plate') type.value = 'condition';
      const wrapper = document.createElement('div');
      wrapper.className = 'input-group';
      photo.replaceWith(wrapper);
      wrapper.append(photo);
      const button = document.createElement('button');
      button.type = 'button'; button.className = 'btn btn-secondary'; button.dataset.companionDraftPhoto = '1';
      button.dataset.companionPhotoBound = '1';
      button.title = 'Foto aus der Companion-App übernehmen';
      button.innerHTML = '<i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i><span class="visually-hidden">Companion-Foto auswählen</span>';
      wrapper.append(button);
      const paste = form.querySelector('[data-device-photo-paste]');
      paste?.addEventListener('paste', (event) => {
        const item = [...(event.clipboardData?.items || [])].find((candidate) => candidate.type.startsWith('image/'));
        if (!item) return;
        event.preventDefault();
        const blob = item.getAsFile(); if (!blob) return;
        const transfer = new DataTransfer(); transfer.items.add(new File([blob], `companion-foto-${Date.now()}.${blob.type === 'image/png' ? 'png' : 'jpg'}`, {type: blob.type}));
        photo.files = transfer.files;
        paste.textContent = 'Bild eingefügt – jetzt Foto hochladen.';
      });
      if (!root || root.dataset.hasActiveConnection !== '1') button.classList.add('d-none');
      form.querySelectorAll('[data-companion-draft-photo]').forEach((choice) => choice.classList.toggle('d-none', !root || root.dataset.hasActiveConnection !== '1'));
      button.addEventListener('click', () => {
        openDraftPhotoChoices(button, form, root);
      });
    });
  };
  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-companion-draft-photo]');
    if (!button || button.dataset.companionPhotoBound) return;
    const form = button.closest('form.device-form');
    const root = document.querySelector('[data-companion-inbox]');
    if (!form || !root || root.dataset.hasActiveConnection !== '1') return;
    openDraftPhotoChoices(button, form, root);
  });
  document.addEventListener('click', (event) => {
    const choice = event.target.closest('[data-companion-choose]');
    if (!choice) return;
    const root = document.querySelector('[data-companion-inbox]');
    const input = document.getElementById(choice.dataset.companionTarget || '');
    if (!root || !input) return;
    applyValue(input, choice.dataset.companionValue || '');
    consume(root, choice.dataset.companionChoose, input.name || input.id || 'Feld');
    closePopovers();
  });
  document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-companion-copy]');
    if (!button) return;
    const value = button.dataset.companionCopy || '';
    try {
      if (navigator.clipboard?.writeText) await navigator.clipboard.writeText(value);
      else {
        const input = document.createElement('textarea'); input.value = value; input.style.position = 'fixed'; input.style.opacity = '0';
        document.body.append(input); input.select(); document.execCommand('copy'); input.remove();
      }
      const previous = button.textContent;
      button.textContent = 'In Zwischenablage kopiert';
      window.setTimeout(() => { button.textContent = previous; }, 1500);
    } catch (_) {
      window.prompt('Bitte kopieren:', value);
    }
  });
  document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-companion-copy-photo]');
    if (!button) return;
    const root = document.querySelector('[data-companion-inbox]');
    if (!root) return;
    const previous = button.innerHTML;
    try {
      const response = await fetch(`${root.dataset.inboxUrl}/${button.dataset.companionCopyPhoto}/foto`, {headers: {'Accept': 'image/*'}});
      if (!response.ok) throw new Error('Foto konnte nicht geladen werden.');
      const blob = await response.blob();
      if (!navigator.clipboard?.write || typeof ClipboardItem === 'undefined') throw new Error('Dieser Browser kann Bilder nicht direkt in die Zwischenablage kopieren.');
      const png = await clipboardPng(blob);
      await navigator.clipboard.write([new ClipboardItem({'image/png': png})]);
      button.innerHTML = '<i class="fa-solid fa-check me-1" aria-hidden="true"></i>Bild kopiert';
      window.setTimeout(() => { button.innerHTML = previous; }, 1500);
    } catch (failure) {
      window.alert(String(failure.message || failure));
    }
  });
  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-companion-photo-adopt]');
    if (!button) return;
    const modal = document.getElementById('companion-photo-adopt-modal');
    const form = modal?.querySelector('[data-companion-photo-adopt-form]');
    if (!modal || !form || !window.bootstrap) return;
    form.querySelector('[name="item_id"]').value = button.dataset.companionPhotoAdopt || '';
    form.querySelector('[data-companion-photo-adopt-error]')?.classList.add('d-none');
    window.bootstrap.Modal.getOrCreateInstance(modal).show();
  });
  document.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-companion-photo-adopt-form]');
    if (!form) return;
    event.preventDefault();
    const root = document.querySelector('[data-companion-inbox]');
    const itemId = form.querySelector('[name="item_id"]')?.value || '';
    const error = form.querySelector('[data-companion-photo-adopt-error]');
    if (!root || !itemId) return;
    try {
      const response = await fetch(`${root.dataset.inboxUrl}/${itemId}/foto-uebernehmen`, {method: 'POST', headers: {'Accept': 'text/html'}, body: new FormData(form)});
      const html = await response.text();
      if (!response.ok) throw new Error(html || 'Foto konnte nicht zugeordnet werden.');
      window.bootstrap?.Modal.getInstance(form.closest('.modal'))?.hide();
      refresh(root);
    } catch (failure) {
      if (error) { error.textContent = String(failure.message || failure); error.classList.remove('d-none'); }
    }
  });
  document.addEventListener('click', async (event) => {
    const choice = event.target.closest('[data-companion-draft-photo-choose]');
    if (!choice || !activeDraftForm) return;
    const root = document.querySelector('[data-companion-inbox]');
    if (!root) return;
    const itemId = choice.dataset.companionDraftPhotoChoose;
    closeDraftPhotoPopovers();
    choice.disabled = true;
    try {
      const body = new URLSearchParams();
      if (activeDraftType) body.set('media_type', activeDraftType);
      const response = await fetch(`${root.dataset.inboxUrl}/${itemId}/foto-zu-entwurf`, {method: 'POST', headers: {'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded'}, body});
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.error || 'Companion-Foto konnte nicht übernommen werden.');
      const token = activeDraftForm.querySelector('[data-draft-photo-token]');
      if (token) token.value = data.token || '';
      const type = activeDraftForm.querySelector('[name="new_device_photo_type"]');
      if (type && data.media_type) { type.value = data.media_type; type.dispatchEvent(new Event('change', {bubbles: true})); }
      const result = activeDraftForm.querySelector('[data-draft-photo-result]');
      if (result) {
        const proposal = data.proposal || null;
        const fields = proposal ? ['manufacturer','device_model','name','serial_number','inventory_number'].filter((key) => String(proposal[key] || '').trim() !== '').map((key) => `<li><strong>${key === 'name' ? 'Bezeichnung' : key === 'device_model' ? 'Modell' : key === 'manufacturer' ? 'Hersteller' : key === 'serial_number' ? 'Seriennummer' : 'Inventar'}:</strong> ${escapeHtml(proposal[key])}</li>`).join('') : '';
        if (proposal && fields) result.innerHTML = `<div class="alert alert-success py-2 mb-0"><strong><i class="fa-solid fa-wand-magic-sparkles me-1" aria-hidden="true"></i>Typenschildvorschlag bereit</strong><ul class="mb-2 mt-1">${fields}</ul><button class="btn btn-sm btn-primary" type="button" data-companion-draft-apply><i class="fa-solid fa-arrow-down me-1" aria-hidden="true"></i>Vorschlag übernehmen</button></div>`;
        else result.innerHTML = '<div class="alert alert-success py-2 mb-0"><i class="fa-solid fa-check me-1" aria-hidden="true"></i>Companion-Foto wurde in den Geräteentwurf übernommen.</div>';
        const apply = result.querySelector('[data-companion-draft-apply]');
        if (apply && proposal) apply.addEventListener('click', () => {
          ['manufacturer','device_model','name','serial_number','inventory_number'].forEach((key) => { const value = String(proposal[key] || '').trim(); const field = activeDraftForm.querySelector(`[name="${key}"]`); if (!value || !field) return; if (field.tomselect) field.tomselect.setValue(value, true); else { field.value = value; field.dispatchEvent(new Event('change', {bubbles: true})); } });
          apply.disabled = true; apply.innerHTML = '<i class="fa-solid fa-check me-1" aria-hidden="true"></i>Vorschlag übernommen';
        });
      }
      closePopovers(); refresh(root);
    } catch (error) {
      const result = activeDraftForm.querySelector('[data-draft-photo-result]');
      if (result) result.innerHTML = `<div class="alert alert-danger py-2 mb-0">${escapeHtml(error.message || error)}</div>`;
    }
  });
  document.addEventListener('click', (event) => {
    if (!event.target.closest('[data-companion-for], .popover')) closePopovers();
  });
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closePopovers(); });
  const connect = () => {
    const root = document.querySelector('[data-companion-inbox]');
    bindDraftPhotoSelectors(root);
    if (!root || !window.EventSource) return;
    if (stream) stream.close();
    stream = new EventSource(root.dataset.eventsUrl);
    stream.addEventListener('companion-update', () => refresh(root));
    stream.onerror = () => {
      stream?.close(); stream = null;
      clearTimeout(reconnectTimer);
      reconnectTimer = setTimeout(connect, 1700);
    };
    bindInputs(root);
    bindDraftPhotoSelectors(root);
  };
  document.addEventListener('DOMContentLoaded', connect);
  document.body.addEventListener('htmx:afterSwap', (event) => {
    const root = event.target?.matches?.('[data-companion-inbox]') ? event.target : document.querySelector('[data-companion-inbox]');
    if (root) { bindInputs(root); bindDraftPhotoSelectors(root); if (!stream) connect(); }
  });
})();
