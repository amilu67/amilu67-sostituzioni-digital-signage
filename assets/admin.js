document.addEventListener('click', async (e) => {
  const btn = e.target.closest('[data-copy]');
  if (!btn) return;
  const input = document.querySelector(btn.dataset.copy);
  if (!input) return;
  try {
    await navigator.clipboard.writeText(input.value);
    btn.textContent = 'Copiato';
    setTimeout(() => (btn.textContent = 'Copia'), 1400);
  } catch (err) {
    input.select();
    document.execCommand('copy');
  }
});

(() => {
  const form = document.querySelector('[data-amilu67-sds-schedule-form]');
  if (!form) return;

  const tabs = [...form.querySelectorAll('[data-day-tab]')];
  const panels = [...form.querySelectorAll('[data-day-panel]')];

  const activateDay = (day) => {
    tabs.forEach((tab) => {
      const active = tab.dataset.dayTab === String(day);
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    panels.forEach((panel) => panel.classList.toggle('is-active', panel.dataset.dayPanel === String(day)));
  };

  const updateRow = (row) => {
    const select = row.querySelector('[data-activity-select]');
    if (!select) return;
    row.className = row.className.replace(/\bis-[a-z_-]+\b/g, '').trim();
    const util = ['availability','disposition','potenziamento_disponibile','recovery','extra'].includes(select.value);
    row.classList.add(select.value === 'lesson' ? 'is-lesson' : util ? 'is-availability' : select.value ? 'is-other' : 'is-empty');
  };

  const updateTabSummary = (day) => {
    const panel = form.querySelector(`[data-day-panel="${day}"]`);
    const tab = form.querySelector(`[data-day-tab="${day}"]`);
    if (!panel || !tab) return;
    let lessons = 0;
    let availability = 0;
    let other = 0;
    panel.querySelectorAll('[data-activity-select]').forEach((select) => {
      if (select.value === 'lesson') lessons++;
      if (['availability','disposition','potenziamento_disponibile','recovery','extra'].includes(select.value)) availability++;
      else if (select.value && select.value !== 'lesson') other++;
    });
    const small = tab.querySelector('small');
    if (!small) return;
    const parts = [];
    if (lessons) parts.push(`${lessons} lez.`);
    if (availability) parts.push(`${availability} util.`);
    if (other) parts.push(`${other} altre`);
    small.textContent = parts.length ? parts.join(' · ') : 'Nessuna ora';
  };

  tabs.forEach((tab) => tab.addEventListener('click', () => activateDay(tab.dataset.dayTab)));

  form.querySelectorAll('[data-schedule-row]').forEach((row) => {
    updateRow(row);
    const select = row.querySelector('[data-activity-select]');
    select?.addEventListener('change', () => {
      updateRow(row);
      const panel = row.closest('[data-day-panel]');
      if (panel) updateTabSummary(panel.dataset.dayPanel);
    });
  });

  form.querySelector('[data-amilu67-sds-clear-day]')?.addEventListener('click', () => {
    const activePanel = form.querySelector('[data-day-panel].is-active');
    if (!activePanel) return;
    if (!window.confirm('Vuoi svuotare tutte le righe del giorno selezionato? Le modifiche saranno definitive solo dopo il salvataggio.')) return;
    activePanel.querySelectorAll('[data-schedule-row]').forEach((row) => {
      row.querySelectorAll('input').forEach((input) => (input.value = ''));
      const select = row.querySelector('[data-activity-select]');
      if (select) select.value = '';
      updateRow(row);
    });
    updateTabSummary(activePanel.dataset.dayPanel);
  });

  form.querySelector('[data-amilu67-sds-copy-times]')?.addEventListener('click', () => {
    const activePanel = form.querySelector('[data-day-panel].is-active');
    if (!activePanel) return;
    const sourceRows = [...activePanel.querySelectorAll('[data-schedule-row]')];
    panels.forEach((panel) => {
      if (panel === activePanel) return;
      const targetRows = [...panel.querySelectorAll('[data-schedule-row]')];
      sourceRows.forEach((sourceRow, index) => {
        const sourceTimes = sourceRow.querySelectorAll('input[type="time"]');
        const targetTimes = targetRows[index]?.querySelectorAll('input[type="time"]');
        if (!targetTimes || targetTimes.length < 2) return;
        targetTimes[0].value = sourceTimes[0]?.value || '';
        targetTimes[1].value = sourceTimes[1]?.value || '';
      });
    });
    const button = form.querySelector('[data-amilu67-sds-copy-times]');
    const original = button.innerHTML;
    button.innerHTML = '<span class="dashicons dashicons-yes"></span> Fasce copiate';
    setTimeout(() => (button.innerHTML = original), 1500);
  });
})();
