/* EVSU-OC BDMS — shared JS v3 */

var _bdmsLoaderStart = 0;
var _bdmsLoaderMinMs = 2000;
var _bdmsSelectInstances = [];
var _bdmsDateInstances = [];

/* ─── loader ─────────────────────────────────────────── */
window.bdmsShowLoader = function (text, minMs) {
  _bdmsLoaderStart = Date.now();
  _bdmsLoaderMinMs = (typeof minMs === 'number') ? minMs : 2000;

  var el = document.getElementById('bdms-loader');
  if (!el) {
    el = document.createElement('div');
    el.id = 'bdms-loader';
    el.className = 'bdms-loader';
    el.innerHTML =
      '<div class="bdms-loader-drop">' +
        '<div class="bdms-loader-drop-shape"></div>' +
      '</div>' +
      '<p class="bdms-loader-text" id="bdms-loader-text"></p>' +
      '<div class="bdms-loader-dots">' +
        '<span></span><span></span><span></span>' +
      '</div>';
    document.body.appendChild(el);
  }
  document.getElementById('bdms-loader-text').textContent = text || 'Loading…';
  el.classList.add('is-active');
};

window.bdmsLoaderThen = function (callback) {
  var elapsed = Date.now() - _bdmsLoaderStart;
  var remaining = _bdmsLoaderMinMs - elapsed;
  if (remaining > 0) {
    setTimeout(callback, remaining);
  } else {
    callback();
  }
};

window.bdmsHideLoader = function () {
  var el = document.getElementById('bdms-loader');
  if (el) el.classList.remove('is-active');
};

/* ─── sidebar toggle + persistence ──────────────────── */
window.bdmsToggleSidebar = function () {
  var sidebar = document.getElementById('sidebar');
  var page    = document.getElementById('page') || document.querySelector('.page');
  if (!sidebar) return;

  sidebar.classList.toggle('is-open');
  var isOpen = sidebar.classList.contains('is-open');
  if (page) page.classList.toggle('shifted', isOpen);

  try { localStorage.setItem('bdms_sidebar', isOpen ? 'open' : 'closed'); } catch(e) {}
};
window.toggleSidebar = window.bdmsToggleSidebar;

/* restore sidebar state from localStorage */
function _bdmsRestoreSidebar() {
  var sidebar = document.getElementById('sidebar');
  var page    = document.getElementById('page') || document.querySelector('.page');
  if (!sidebar) return;

  var saved;
  try { saved = localStorage.getItem('bdms_sidebar'); } catch(e) {}
  var isCompactViewport = window.innerWidth <= 960;

  if (saved === 'closed' || (saved !== 'open' && isCompactViewport)) {
    sidebar.classList.remove('is-open');
    if (page) page.classList.remove('shifted');
  } else {
    sidebar.classList.add('is-open');
    if (page) page.classList.add('shifted');
  }
}

/* close sidebar when clicking outside */
document.addEventListener('click', function (e) {
  if (window.innerWidth > 960) return;
  var sidebar = document.getElementById('sidebar');
  if (!sidebar) return;
  if (!sidebar.contains(e.target) && !e.target.closest('[onclick*="Sidebar"]') && !e.target.closest('.appbar-nav-toggle')) {
    sidebar.classList.remove('is-open');
    var page = document.getElementById('page') || document.querySelector('.page');
    if (page) page.classList.remove('shifted');
    try { localStorage.setItem('bdms_sidebar', 'closed'); } catch(e2) {}
  }
});

/* ─── toast / snackbar system ────────────────────────── */
var _toastIcons = {
  warning: 'fa-exclamation-triangle',
  success: 'fa-check-circle',
  error:   'fa-times-circle',
  info:    'fa-info-circle'
};

function _bdmsGetToastContainer() {
  var c = document.getElementById('bdms-toast-container');
  if (!c) {
    c = document.createElement('div');
    c.id = 'bdms-toast-container';
    document.body.appendChild(c);
  }
  return c;
}

/**
 * Show a toast notification.
 * @param {string} title
 * @param {string} message  (HTML supported)
 * @param {string} type     'warning'|'success'|'error'|'info'
 * @param {number} duration ms. 0 = persistent. Default: 5000 for success/error, 0 for warning/info.
 */
window.bdmsToast = function (title, message, type, duration) {
  type = type || 'info';
  if (typeof duration === 'undefined') {
    duration = (type === 'success' || type === 'error') ? 5000 : 0;
  }

  var container = _bdmsGetToastContainer();
  var toast = document.createElement('div');
  toast.className = 'bdms-toast bdms-toast--' + type;
  toast.innerHTML =
    '<div class="bdms-toast-icon"><i class="fa ' + (_toastIcons[type] || 'fa-info-circle') + '"></i></div>' +
    '<div class="bdms-toast-body">' +
      '<div class="bdms-toast-title">' + title + '</div>' +
      '<div class="bdms-toast-msg">' + message + '</div>' +
    '</div>' +
    '<button class="bdms-toast-close" title="Dismiss">&times;</button>';

  toast.querySelector('.bdms-toast-close').addEventListener('click', function () {
    _bdmsDismissToast(toast);
  });

  container.appendChild(toast);

  if (duration > 0) {
    setTimeout(function () { _bdmsDismissToast(toast); }, duration);
  }

  return toast;
};

function _bdmsDismissToast(toast) {
  if (!toast || toast.classList.contains('is-leaving')) return;
  toast.classList.add('is-leaving');
  setTimeout(function () { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 300);
}

function _bdmsParseDateValue(value) {
  if (!value) return null;
  var parts = value.split('-');
  if (parts.length !== 3) return null;
  var year = parseInt(parts[0], 10);
  var month = parseInt(parts[1], 10) - 1;
  var day = parseInt(parts[2], 10);
  var date = new Date(year, month, day);
  if (isNaN(date.getTime())) return null;
  if (date.getFullYear() !== year || date.getMonth() !== month || date.getDate() !== day) return null;
  return date;
}

function _bdmsFormatDateValue(date) {
  var year = date.getFullYear();
  var month = String(date.getMonth() + 1).padStart(2, '0');
  var day = String(date.getDate()).padStart(2, '0');
  return year + '-' + month + '-' + day;
}

function _bdmsSameDate(left, right) {
  return !!left && !!right && left.getFullYear() === right.getFullYear() && left.getMonth() === right.getMonth() && left.getDate() === right.getDate();
}

function _bdmsStartOfMonth(date) {
  return new Date(date.getFullYear(), date.getMonth(), 1);
}

function _bdmsAddDays(date, amount) {
  return new Date(date.getFullYear(), date.getMonth(), date.getDate() + amount);
}

function _bdmsMonthLabel(date) {
  return new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' }).format(date);
}

function _bdmsDateLabel(date) {
  return new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: 'numeric' }).format(date);
}

function _bdmsDateToUtcStamp(date) {
  return Date.UTC(date.getFullYear(), date.getMonth(), date.getDate());
}

function _bdmsGetDateBounds(select) {
  var min = _bdmsParseDateValue(select.getAttribute('min'));
  var max = _bdmsParseDateValue(select.getAttribute('max'));
  return { min: min, max: max };
}

function _bdmsClampDate(date, bounds) {
  if (bounds.min && _bdmsDateToUtcStamp(date) < _bdmsDateToUtcStamp(bounds.min)) return new Date(bounds.min.getFullYear(), bounds.min.getMonth(), bounds.min.getDate());
  if (bounds.max && _bdmsDateToUtcStamp(date) > _bdmsDateToUtcStamp(bounds.max)) return new Date(bounds.max.getFullYear(), bounds.max.getMonth(), bounds.max.getDate());
  return date;
}

/* ─── custom date pickers ───────────────────────────── */
function _bdmsCloseDate(instance) {
  if (!instance || !instance.shell || !instance.shell.classList.contains('is-open')) return;
  instance.shell.classList.remove('is-open');
  instance.trigger.setAttribute('aria-expanded', 'false');
}

function _bdmsCloseAllDates(exceptInstance) {
  _bdmsDateInstances.forEach(function (instance) {
    if (instance !== exceptInstance) _bdmsCloseDate(instance);
  });
}

function _bdmsRefreshDateTrigger(instance) {
  var value = instance.input.value;
  if (!value) {
    instance.label.textContent = instance.placeholder;
    instance.label.classList.add('muted');
  } else {
    var selected = _bdmsParseDateValue(value);
    instance.label.textContent = selected ? _bdmsDateLabel(selected) : value;
    instance.label.classList.remove('muted');
  }
}

function _bdmsGetFocusableDateButtons(instance) {
  return instance.menu.querySelectorAll('.bdms-date-day:not(.is-disabled)');
}

function _bdmsRenderDateGrid(instance, focusDate) {
  var monthStart = _bdmsStartOfMonth(instance.viewDate);
  var firstDayIndex = monthStart.getDay();
  var gridStart = _bdmsAddDays(monthStart, -firstDayIndex);
  var selectedDate = _bdmsParseDateValue(instance.input.value);
  var today = new Date();
  var bounds = _bdmsGetDateBounds(instance.input);
  var grid = instance.grid;
  var monthLabel = instance.monthLabel;

  monthLabel.textContent = _bdmsMonthLabel(monthStart);
  grid.innerHTML = '';

  for (var index = 0; index < 42; index += 1) {
    var current = _bdmsAddDays(gridStart, index);
    var dateValue = _bdmsFormatDateValue(current);
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'bdms-date-day';
    button.textContent = String(current.getDate());
    button.setAttribute('data-date', dateValue);
    button.setAttribute('aria-label', new Intl.DateTimeFormat(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }).format(current));

    if (current.getMonth() !== monthStart.getMonth()) button.classList.add('is-outside');
    if (_bdmsSameDate(current, today)) button.classList.add('is-today');
    if (selectedDate && _bdmsSameDate(current, selectedDate)) button.classList.add('is-selected');
    if ((bounds.min && _bdmsDateToUtcStamp(current) < _bdmsDateToUtcStamp(bounds.min)) || (bounds.max && _bdmsDateToUtcStamp(current) > _bdmsDateToUtcStamp(bounds.max))) {
      button.classList.add('is-disabled');
      button.disabled = true;
    }

    button.addEventListener('click', function () {
      if (this.disabled) return;
      instance.input.value = this.getAttribute('data-date') || '';
      instance.input.dispatchEvent(new Event('input', { bubbles: true }));
      instance.input.dispatchEvent(new Event('change', { bubbles: true }));
      _bdmsRefreshDateTrigger(instance);
      _bdmsCloseDate(instance);
      instance.trigger.focus();
    });

    grid.appendChild(button);
  }

  var focusTarget = focusDate || selectedDate || today;
  var targetButton = null;
  grid.querySelectorAll('.bdms-date-day').forEach(function (button) {
    if (button.getAttribute('data-date') === _bdmsFormatDateValue(focusTarget)) {
      targetButton = button;
    }
    button.classList.remove('is-focused');
  });
  if (targetButton) {
    targetButton.classList.add('is-focused');
    instance.focusedDate = _bdmsParseDateValue(targetButton.getAttribute('data-date'));
  } else {
    instance.focusedDate = focusTarget;
  }
}

function _bdmsBuildDateMenu(instance) {
  var menu = document.createElement('div');
  menu.className = 'bdms-date-menu';

  var panel = document.createElement('div');
  panel.className = 'bdms-date-panel';

  var header = document.createElement('div');
  header.className = 'bdms-date-header';

  var monthLabel = document.createElement('div');
  monthLabel.className = 'bdms-date-month';

  var nav = document.createElement('div');
  nav.className = 'bdms-date-nav';

  var prevButton = document.createElement('button');
  prevButton.type = 'button';
  prevButton.setAttribute('aria-label', 'Previous month');
  prevButton.innerHTML = '<i class="fa fa-chevron-up"></i>';

  // quick year jump (previous year)
  var prevYearButton = document.createElement('button');
  prevYearButton.type = 'button';
  prevYearButton.setAttribute('aria-label', 'Previous year');
  prevYearButton.title = 'Jump back one year';
  prevYearButton.innerHTML = '<i class="fa fa-angle-double-up"></i>';

  var nextButton = document.createElement('button');
  nextButton.type = 'button';
  nextButton.setAttribute('aria-label', 'Next month');
  nextButton.innerHTML = '<i class="fa fa-chevron-down"></i>';

  // quick year jump (next year)
  var nextYearButton = document.createElement('button');
  nextYearButton.type = 'button';
  nextYearButton.setAttribute('aria-label', 'Next year');
  nextYearButton.title = 'Jump forward one year';
  nextYearButton.innerHTML = '<i class="fa fa-angle-double-down"></i>';

  nav.appendChild(prevButton);
  nav.appendChild(prevYearButton);
  nav.appendChild(nextButton);
  nav.appendChild(nextYearButton);
  header.appendChild(monthLabel);
  header.appendChild(nav);

  var weekdays = document.createElement('div');
  weekdays.className = 'bdms-date-weekdays';
  ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'].forEach(function (name) {
    var weekday = document.createElement('div');
    weekday.className = 'bdms-date-weekday';
    weekday.textContent = name;
    weekdays.appendChild(weekday);
  });

  var grid = document.createElement('div');
  grid.className = 'bdms-date-grid';

  var footer = document.createElement('div');
  footer.className = 'bdms-date-footer';

  var clearButton = document.createElement('button');
  clearButton.type = 'button';
  clearButton.textContent = 'Clear';

  var todayButton = document.createElement('button');
  todayButton.type = 'button';
  todayButton.textContent = 'Today';

  footer.appendChild(clearButton);
  footer.appendChild(todayButton);

  panel.appendChild(header);
  panel.appendChild(weekdays);
  panel.appendChild(grid);
  panel.appendChild(footer);
  menu.appendChild(panel);

  instance.monthLabel = monthLabel;
  instance.grid = grid;

  prevButton.addEventListener('click', function () {
    instance.viewDate = new Date(instance.viewDate.getFullYear(), instance.viewDate.getMonth() - 1, 1);
    _bdmsRenderDateGrid(instance, instance.focusedDate);
  });
  nextButton.addEventListener('click', function () {
    instance.viewDate = new Date(instance.viewDate.getFullYear(), instance.viewDate.getMonth() + 1, 1);
    _bdmsRenderDateGrid(instance, instance.focusedDate);
  });
  prevYearButton.addEventListener('click', function () {
    instance.viewDate = new Date(instance.viewDate.getFullYear() - 1, instance.viewDate.getMonth(), 1);
    _bdmsRenderDateGrid(instance, instance.focusedDate);
  });
  nextYearButton.addEventListener('click', function () {
    instance.viewDate = new Date(instance.viewDate.getFullYear() + 1, instance.viewDate.getMonth(), 1);
    _bdmsRenderDateGrid(instance, instance.focusedDate);
  });
  clearButton.addEventListener('click', function () {
    instance.input.value = '';
    instance.input.dispatchEvent(new Event('input', { bubbles: true }));
    instance.input.dispatchEvent(new Event('change', { bubbles: true }));
    _bdmsRefreshDateTrigger(instance);
    _bdmsCloseDate(instance);
    instance.trigger.focus();
  });
  todayButton.addEventListener('click', function () {
    var now = new Date();
    instance.input.value = _bdmsFormatDateValue(now);
    instance.viewDate = new Date(now.getFullYear(), now.getMonth(), 1);
    instance.focusedDate = now;
    instance.input.dispatchEvent(new Event('input', { bubbles: true }));
    instance.input.dispatchEvent(new Event('change', { bubbles: true }));
    _bdmsRefreshDateTrigger(instance);
    _bdmsRenderDateGrid(instance, now);
    _bdmsCloseDate(instance);
    instance.trigger.focus();
  });

  return menu;
}

function _bdmsEnhanceDateInput(input) {
  if (!input || input.dataset.bdmsEnhanced === '1' || input.type !== 'date') return;

  var shell = document.createElement('div');
  shell.className = 'bdms-date-shell';
  if (input.disabled) shell.classList.add('is-disabled');

  if (input.style.width) shell.style.width = input.style.width;
  if (input.style.maxWidth) shell.style.maxWidth = input.style.maxWidth;
  if (input.style.minWidth) shell.style.minWidth = input.style.minWidth;
  if (input.style.flex) shell.style.flex = input.style.flex;

  var trigger = document.createElement('button');
  trigger.type = 'button';
  trigger.className = 'bdms-date-trigger';
  trigger.setAttribute('aria-haspopup', 'dialog');
  trigger.setAttribute('aria-expanded', 'false');

  var label = document.createElement('span');
  label.className = 'bdms-date-trigger-text';

  var icon = document.createElement('i');
  icon.className = 'fa fa-calendar-alt bdms-date-trigger-icon';

  trigger.appendChild(label);
  trigger.appendChild(icon);

  var parent = input.parentNode;
  parent.insertBefore(shell, input);
  shell.appendChild(input);
  shell.appendChild(trigger);

  input.classList.add('bdms-date-fallback');
  input.dataset.bdmsEnhanced = '1';
  input.setAttribute('tabindex', '-1');

  var currentValue = _bdmsParseDateValue(input.value);
  var initialView = currentValue || new Date();
  var instance = {
    input: input,
    shell: shell,
    trigger: trigger,
    label: label,
    menu: null,
    placeholder: input.getAttribute('data-placeholder') || 'Choose a date',
    viewDate: new Date(initialView.getFullYear(), initialView.getMonth(), 1),
    focusedDate: currentValue || new Date()
  };

  instance.menu = _bdmsBuildDateMenu(instance);
  shell.appendChild(instance.menu);
  _bdmsRenderDateGrid(instance, instance.focusedDate);
  _bdmsRefreshDateTrigger(instance);

  trigger.addEventListener('click', function (event) {
    event.preventDefault();
    event.stopPropagation();
    if (shell.classList.contains('is-open')) {
      _bdmsCloseDate(instance);
    } else {
      _bdmsCloseAllDates(instance);
      shell.classList.add('is-open');
      trigger.setAttribute('aria-expanded', 'true');
      var selected = _bdmsParseDateValue(input.value);
      if (selected) {
        instance.viewDate = new Date(selected.getFullYear(), selected.getMonth(), 1);
      }
      _bdmsRenderDateGrid(instance, selected || instance.focusedDate || new Date());
    }
  });

  trigger.addEventListener('keydown', function (event) {
    if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      if (!shell.classList.contains('is-open')) {
        trigger.click();
      }
      return;
    }
    if (event.key === 'Escape') {
      event.preventDefault();
      _bdmsCloseDate(instance);
    }
  });

  input.addEventListener('change', function () {
    var selected = _bdmsParseDateValue(input.value);
    if (selected) {
      instance.viewDate = new Date(selected.getFullYear(), selected.getMonth(), 1);
      instance.focusedDate = selected;
      _bdmsRenderDateGrid(instance, selected);
    }
    _bdmsRefreshDateTrigger(instance);
  });

  input.addEventListener('input', function () {
    _bdmsRefreshDateTrigger(instance);
  });

  _bdmsDateInstances.push(instance);
}

function _bdmsInitCustomDateInputs() {
  var inputs = document.querySelectorAll('input[type="date"]');
  inputs.forEach(function (input) {
    _bdmsEnhanceDateInput(input);
  });

  document.addEventListener('click', function (event) {
    _bdmsDateInstances.forEach(function (instance) {
      if (!instance.shell.contains(event.target)) {
        _bdmsCloseDate(instance);
      }
    });
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      _bdmsCloseAllDates();
    }
  });
}

/* ─── custom selects ────────────────────────────────── */
function _bdmsCloseSelect(instance) {
  if (!instance || !instance.shell || !instance.shell.classList.contains('is-open')) return;
  instance.shell.classList.remove('is-open');
  instance.trigger.setAttribute('aria-expanded', 'false');
}

function _bdmsCloseAllSelects(exceptInstance) {
  _bdmsSelectInstances.forEach(function (instance) {
    if (instance !== exceptInstance) _bdmsCloseSelect(instance);
  });
}

function _bdmsRefreshSelectTrigger(instance) {
  var select = instance.select;
  var option = select.options[select.selectedIndex];
  var label = option ? option.textContent : (select.dataset.placeholder || 'Choose an option');
  instance.label.textContent = label;
  instance.menu.querySelectorAll('.bdms-select-option').forEach(function (button, index) {
    var optionEl = select.options[index];
    var isSelected = optionEl && optionEl.selected;
    button.classList.toggle('is-selected', isSelected);
    button.setAttribute('aria-selected', isSelected ? 'true' : 'false');
  });
}

function _bdmsOpenSelect(instance) {
  if (!instance || instance.select.disabled) return;
  _bdmsCloseAllSelects(instance);
  instance.shell.classList.add('is-open');
  instance.trigger.setAttribute('aria-expanded', 'true');
}

function _bdmsBuildSelectMenu(instance) {
  var select = instance.select;
  var options = Array.prototype.slice.call(select.options);
  var menu = document.createElement('div');
  menu.className = 'bdms-select-menu';

  var optionsWrap = document.createElement('div');
  optionsWrap.className = 'bdms-select-options';

  if (!options.length) {
    var empty = document.createElement('div');
    empty.className = 'bdms-select-empty';
    empty.textContent = 'No options available';
    optionsWrap.appendChild(empty);
  } else {
    options.forEach(function (optionEl, index) {
      if (optionEl.disabled || optionEl.parentElement && optionEl.parentElement.tagName === 'OPTGROUP' && optionEl.parentElement.disabled) return;
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'bdms-select-option';
      button.textContent = optionEl.textContent;
      button.setAttribute('role', 'option');
      button.setAttribute('aria-selected', optionEl.selected ? 'true' : 'false');
      if (optionEl.selected) button.classList.add('is-selected');
      button.addEventListener('click', function () {
        if (select.value !== optionEl.value) {
          select.value = optionEl.value;
          select.dispatchEvent(new Event('input', { bubbles: true }));
          select.dispatchEvent(new Event('change', { bubbles: true }));
        }
        _bdmsRefreshSelectTrigger(instance);
        _bdmsCloseSelect(instance);
        instance.trigger.focus();
      });
      button.addEventListener('mouseenter', function () {
        optionsWrap.querySelectorAll('.bdms-select-option.is-focused').forEach(function (item) {
          item.classList.remove('is-focused');
        });
        button.classList.add('is-focused');
      });
      optionsWrap.appendChild(button);
    });
  }

  menu.appendChild(optionsWrap);
  return menu;
}

function _bdmsEnhanceSelect(select) {
  if (!select || select.dataset.bdmsEnhanced === '1' || select.multiple || select.size > 1) return;
  if (select.closest('.modal, .swal2-container, .swal2-popup')) return;

  var shell = document.createElement('div');
  shell.className = 'bdms-select-shell';
  if (select.disabled) shell.classList.add('is-disabled');

  if (select.style.width) shell.style.width = select.style.width;
  if (select.style.maxWidth) shell.style.maxWidth = select.style.maxWidth;
  if (select.style.minWidth) shell.style.minWidth = select.style.minWidth;
  if (select.style.flex) shell.style.flex = select.style.flex;

  var trigger = document.createElement('button');
  trigger.type = 'button';
  trigger.className = 'bdms-select-trigger';
  trigger.setAttribute('aria-haspopup', 'listbox');
  trigger.setAttribute('aria-expanded', 'false');

  var label = document.createElement('span');
  label.className = 'bdms-select-trigger-label';

  var icon = document.createElement('i');
  icon.className = 'fa fa-chevron-down bdms-select-trigger-icon';

  trigger.appendChild(label);
  trigger.appendChild(icon);

  var parent = select.parentNode;
  var nextSibling = select.nextSibling;
  parent.insertBefore(shell, select);
  shell.appendChild(select);
  shell.appendChild(trigger);

  select.classList.add('bdms-select-fallback');
  select.dataset.bdmsEnhanced = '1';
  select.setAttribute('tabindex', '-1');

  var instance = {
    select: select,
    shell: shell,
    trigger: trigger,
    label: label,
    menu: null
  };
  instance.menu = _bdmsBuildSelectMenu(instance);
  shell.appendChild(instance.menu);
  _bdmsRefreshSelectTrigger(instance);

  trigger.addEventListener('click', function (event) {
    event.preventDefault();
    event.stopPropagation();
    if (shell.classList.contains('is-open')) {
      _bdmsCloseSelect(instance);
    } else {
      _bdmsOpenSelect(instance);
    }
  });

  trigger.addEventListener('keydown', function (event) {
    var options = instance.menu.querySelectorAll('.bdms-select-option');
    var currentIndex = Array.prototype.findIndex.call(options, function (button) {
      return button.classList.contains('is-focused');
    });

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      if (!shell.classList.contains('is-open')) _bdmsOpenSelect(instance);
      if (!options.length) return;
      if (currentIndex < 0) currentIndex = Array.prototype.findIndex.call(options, function (button) { return button.classList.contains('is-selected'); });
      var nextIndex = event.key === 'ArrowDown' ? currentIndex + 1 : currentIndex - 1;
      if (nextIndex < 0) nextIndex = options.length - 1;
      if (nextIndex >= options.length) nextIndex = 0;
      options.forEach(function (button) { button.classList.remove('is-focused'); });
      options[nextIndex].classList.add('is-focused');
      options[nextIndex].scrollIntoView({ block: 'nearest' });
      return;
    }

    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      if (!shell.classList.contains('is-open')) {
        _bdmsOpenSelect(instance);
      } else if (currentIndex >= 0 && options[currentIndex]) {
        options[currentIndex].click();
      }
      return;
    }

    if (event.key === 'Escape') {
      event.preventDefault();
      _bdmsCloseSelect(instance);
      return;
    }
  });

  select.addEventListener('change', function () {
    _bdmsRefreshSelectTrigger(instance);
  });

  select.addEventListener('input', function () {
    _bdmsRefreshSelectTrigger(instance);
  });

  select.addEventListener('disabledchange', function () {
    shell.classList.toggle('is-disabled', select.disabled);
    trigger.disabled = select.disabled;
  });

  _bdmsSelectInstances.push(instance);
}

function _bdmsInitCustomSelects() {
  var selects = document.querySelectorAll('select');
  selects.forEach(function (select) {
    _bdmsEnhanceSelect(select);
  });

  document.addEventListener('click', function (event) {
    _bdmsSelectInstances.forEach(function (instance) {
      if (!instance.shell.contains(event.target)) {
        _bdmsCloseSelect(instance);
      }
    });
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      _bdmsCloseAllSelects();
    }
  });
}

/* ─── table sorting ─────────────────────────────────── */
function _bdmsCleanSortValue(value) {
  return String(value || '').replace(/\s+/g, ' ').trim();
}

function _bdmsIsNumericSortValue(value) {
  return /^-?[\d,.]+$/.test(value) && !isNaN(parseFloat(value.replace(/,/g, '')));
}

function _bdmsIsDateSortValue(value) {
  if (!value) return false;
  var normalized = value.replace(/\s+/g, ' ').trim();
  return !isNaN(Date.parse(normalized)) && /[\d/\-.,]/.test(normalized);
}

function _bdmsDetectSortType(values) {
  var sampleValues = values.filter(function (value) { return value !== ''; }).slice(0, 8);
  if (!sampleValues.length) return 'text';
  if (sampleValues.every(_bdmsIsNumericSortValue)) return 'number';
  if (sampleValues.every(_bdmsIsDateSortValue)) return 'date';
  return 'text';
}

function _bdmsGetSortedCellValue(cell, type) {
  var raw = _bdmsCleanSortValue(cell && (cell.getAttribute('data-sort-value') || cell.textContent || ''));
  if (!raw) return null;
  if (type === 'number') return parseFloat(raw.replace(/,/g, ''));
  if (type === 'date') return Date.parse(raw);
  return raw.toLowerCase();
}

function _bdmsInitTableSorting() {
  var tables = document.querySelectorAll('table');
  var collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });

  tables.forEach(function (table) {
    if (table.dataset.bdmsSortedInit === '1') return;
    var headerRow = table.tHead && table.tHead.rows.length ? table.tHead.rows[0] : null;
    var body = table.tBodies && table.tBodies.length ? table.tBodies[0] : null;
    if (!headerRow || !body) return;

    var headers = Array.prototype.slice.call(headerRow.cells);
    var rows = Array.prototype.slice.call(body.rows);
    if (!rows.length) return;

    headers.forEach(function (header, index) {
      var headerText = _bdmsCleanSortValue(header.textContent).toLowerCase();
      var isActionHeader = /action/.test(headerText) || header.querySelector('a,button,input,select,textarea');
      if (header.colSpan > 1 || isActionHeader) return;

      var sampleValues = rows.slice(0, 6).map(function (row) {
        var cell = row.cells[index];
        return _bdmsCleanSortValue(cell ? (cell.getAttribute('data-sort-value') || cell.textContent) : '');
      }).filter(function (value) { return value !== ''; });

      if (!sampleValues.length) return;

      var sortType = _bdmsDetectSortType(sampleValues);
      header.classList.add('bdms-sortable');
      header.setAttribute('role', 'button');
      header.setAttribute('tabindex', '0');
      header.setAttribute('aria-sort', 'none');

      function sortRows(direction) {
        var sortedRows = rows.slice().sort(function (leftRow, rightRow) {
          var leftCell = leftRow.cells[index];
          var rightCell = rightRow.cells[index];
          var leftValue = _bdmsGetSortedCellValue(leftCell, sortType);
          var rightValue = _bdmsGetSortedCellValue(rightCell, sortType);

          if (leftValue === null && rightValue === null) return 0;
          if (leftValue === null) return 1;
          if (rightValue === null) return -1;

          var comparison;
          if (sortType === 'number') {
            comparison = leftValue - rightValue;
          } else if (sortType === 'date') {
            comparison = leftValue - rightValue;
          } else {
            comparison = collator.compare(String(leftValue), String(rightValue));
          }

          if (comparison === 0) {
            comparison = parseInt(leftRow.getAttribute('data-bdms-original-index') || '0', 10) - parseInt(rightRow.getAttribute('data-bdms-original-index') || '0', 10);
          }

          return direction === 'asc' ? comparison : -comparison;
        });

        sortedRows.forEach(function (row) {
          body.appendChild(row);
        });

        headers.forEach(function (otherHeader) {
          otherHeader.classList.remove('is-sorted-asc', 'is-sorted-desc');
          otherHeader.setAttribute('aria-sort', 'none');
        });
        header.classList.add(direction === 'asc' ? 'is-sorted-asc' : 'is-sorted-desc');
        header.setAttribute('aria-sort', direction === 'asc' ? 'ascending' : 'descending');
      }

      header.addEventListener('click', function () {
        var currentDirection = header.classList.contains('is-sorted-asc') ? 'asc' : (header.classList.contains('is-sorted-desc') ? 'desc' : null);
        var nextDirection = currentDirection === 'asc' ? 'desc' : 'asc';
        sortRows(nextDirection);
      });

      header.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          header.click();
        }
      });
    });

    rows.forEach(function (row, originalIndex) {
      row.setAttribute('data-bdms-original-index', String(originalIndex));
    });

    table.dataset.bdmsSortedInit = '1';
  });
}

/* ─── profile menu dropdown ────────────────────────── */
var _bdmsProfileMenuOpen = false;

function _bdmsCloseProfileMenu() {
  var dropdown = document.querySelector('[data-bdms-profile-dropdown]');
  if (!dropdown) return;
  dropdown.classList.remove('is-open');
  var toggle = dropdown.querySelector('[data-bdms-profile-toggle]');
  if (toggle) toggle.setAttribute('aria-expanded', 'false');
  _bdmsProfileMenuOpen = false;
}

window.bdmsToggleProfileMenu = function (event) {
  if (event) {
    event.preventDefault();
    event.stopPropagation();
  }

  var dropdown = document.querySelector('[data-bdms-profile-dropdown]');
  if (!dropdown) return;

  var shouldOpen = !dropdown.classList.contains('is-open');
  _bdmsCloseProfileMenu();

  if (shouldOpen) {
    dropdown.classList.add('is-open');
    var toggle = dropdown.querySelector('[data-bdms-profile-toggle]');
    if (toggle) toggle.setAttribute('aria-expanded', 'true');
    _bdmsProfileMenuOpen = true;
  }
};

/* ─── logout ─────────────────────────────────────────── */
window.confirmLogout = function (event) {
  if (event) event.preventDefault();

  function doLogout() {
    bdmsShowLoader('Signing out…');
    bdmsLoaderThen(function () {
      window.location.href = 'logout.php';
    });
  }

  if (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') {
    Swal.fire({
      title: 'Sign out?',
      text: 'You will be returned to the sign-in page.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sign out',
      cancelButtonText: 'Cancel',
      reverseButtons: true,
      confirmButtonColor: '#c41e3a',
    }).then(function (r) {
      if (r.isConfirmed) doLogout();
    });
  } else {
    if (window.confirm('Sign out?')) doLogout();
  }
};

var _bdmsPasswordModal = null;

function _bdmsGetPasswordModal() {
  if (_bdmsPasswordModal) return _bdmsPasswordModal;
  _bdmsPasswordModal = document.querySelector('[data-bdms-password-modal]');
  return _bdmsPasswordModal;
}

function _bdmsResetPasswordModal() {
  var modal = _bdmsGetPasswordModal();
  if (!modal) return;
  var form = modal.querySelector('.bdms-password-form');
  if (form) form.reset();
  modal.querySelectorAll('.password-toggle').forEach(function (button) {
    var input = document.getElementById(button.getAttribute('data-target'));
    if (input) input.type = 'password';
    var icon = button.querySelector('i');
    if (icon) icon.className = 'fa fa-eye-slash';
    button.setAttribute('aria-label', 'Show password');
    button.setAttribute('title', 'Show password');
  });
}

function _bdmsTogglePasswordField(button) {
  if (!button) return;
  var targetId = button.getAttribute('data-target');
  var input = targetId ? document.getElementById(targetId) : null;
  if (!input) return;

  var willShow = input.type === 'password';
  input.type = willShow ? 'text' : 'password';

  var icon = button.querySelector('i');
  if (icon) {
    icon.className = willShow ? 'fa fa-eye' : 'fa fa-eye-slash';
  }

  button.setAttribute('aria-label', willShow ? 'Hide password' : 'Show password');
  button.setAttribute('title', willShow ? 'Hide password' : 'Show password');
}

window.bdmsOpenChangePasswordModal = function (event) {
  if (event) {
    event.preventDefault();
    event.stopPropagation();
  }

  var modal = _bdmsGetPasswordModal();
  if (!modal) return;

  _bdmsCloseProfileMenu();
  _bdmsResetPasswordModal();
  modal.classList.add('is-open');
  modal.setAttribute('aria-hidden', 'false');

  var firstInput = modal.querySelector('input[name="current_password"]');
  if (firstInput) {
    setTimeout(function () { firstInput.focus(); }, 0);
  }
};

window.bdmsCloseChangePasswordModal = function () {
  var modal = _bdmsGetPasswordModal();
  if (!modal) return;
  modal.classList.remove('is-open');
  modal.setAttribute('aria-hidden', 'true');
  _bdmsResetPasswordModal();
};

/* ─── login form loader (min 2s before submit) ───────── */
document.addEventListener('DOMContentLoaded', function () {
  _bdmsRestoreSidebar();
  _bdmsInitCustomDateInputs();
  _bdmsInitCustomSelects();
  _bdmsInitTableSorting();

  document.querySelectorAll('[data-bdms-profile-dropdown]').forEach(function (dropdown) {
    var toggleButton = dropdown.querySelector('[data-bdms-profile-toggle]');
    if (toggleButton) toggleButton.setAttribute('aria-expanded', dropdown.classList.contains('is-open') ? 'true' : 'false');
  });

  document.addEventListener('click', function (event) {
    var dropdown = document.querySelector('[data-bdms-profile-dropdown]');
    if (!dropdown || !dropdown.classList.contains('is-open')) return;
    if (!dropdown.contains(event.target)) {
      _bdmsCloseProfileMenu();
    }
  });

  var passwordModal = _bdmsGetPasswordModal();
  if (passwordModal) {
    passwordModal.querySelectorAll('.password-toggle').forEach(function (button) {
      button.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        _bdmsTogglePasswordField(button);
      });
    });

    passwordModal.addEventListener('click', function (event) {
      if (event.target === passwordModal) {
        event.preventDefault();
        event.stopPropagation();
      }
    });
  }

  var loginForm = document.getElementById('login-form');
  if (loginForm) {
    loginForm.addEventListener('submit', function (e) {
      e.preventDefault();
      bdmsShowLoader('Signing in…');
      var form = loginForm;
      bdmsLoaderThen(function () {
        form.submit();
      });
    });
  }

  var params = new URLSearchParams(window.location.search);
  var passwordStatus = params.get('password');
  if (passwordStatus && typeof bdmsToast === 'function') {
    if (passwordStatus === 'success') {
      bdmsToast('Password updated', params.get('message') || 'Your password has been updated successfully.', 'success', 3500);
    } else if (passwordStatus === 'error') {
      bdmsToast('Unable to change password', params.get('message') || 'Please check your current password and try again.', 'error', 4500);
    }
    if (window.history && window.history.replaceState) {
      params.delete('password');
      params.delete('message');
      var nextUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + window.location.hash;
      window.history.replaceState({}, '', nextUrl);
    }
  }
});
