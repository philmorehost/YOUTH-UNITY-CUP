document.addEventListener('click', function (event) {
    const toggle = event.target.closest('[data-password-toggle]');
    if (!toggle) return;

    const input = document.getElementById(toggle.dataset.passwordToggle);
    if (!input) return;

    const reveal = input.type === 'password';
    input.type = reveal ? 'text' : 'password';
    toggle.textContent = reveal ? 'Hide' : 'Show';
    toggle.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
});

const playerCategory = document.querySelector('[data-player-category]');
const playerAge = document.querySelector('[data-player-age]');
if (playerCategory && playerAge) {
    const syncPlayerAge = function () {
        const required = playerCategory.value === 'player';
        playerAge.required = required;
        playerAge.setAttribute('aria-required', required ? 'true' : 'false');
    };
    playerCategory.addEventListener('change', syncPlayerAge);
    syncPlayerAge();
}

const liveSearchInput = document.querySelector('[data-live-search-input]');
const liveSearchPanel = liveSearchInput?.closest('[data-live-search]');
const liveSearchTable = document.querySelector('.ops-wrap .ops-table');
if (liveSearchInput && liveSearchPanel && liveSearchTable?.tBodies[0]) {
    const tbody = liveSearchTable.tBodies[0];
    const resultCount = liveSearchPanel.querySelector('[data-live-search-count]');
    const originalEmptyRows = Array.from(tbody.rows).filter((row) => row.querySelector('.empty-state'));
    const recordRows = Array.from(tbody.rows).filter((row) => !row.querySelector('.empty-state'));
    const noResultsRow = document.createElement('tr');
    noResultsRow.className = 'live-search-empty-row';
    noResultsRow.hidden = true;
    const noResultsCell = document.createElement('td');
    noResultsCell.className = 'empty-state';
    noResultsCell.colSpan = Math.max(1, liveSearchTable.tHead?.rows[0]?.cells.length || 1);
    noResultsCell.textContent = 'No records match your search.';
    noResultsRow.append(noResultsCell);
    tbody.append(noResultsRow);

    const searchableText = (row) => {
        const selectedLabels = Array.from(row.querySelectorAll('select')).map((select) => {
            const selected = select.selectedOptions[0];
            return selected ? selected.textContent || '' : '';
        });
        const copy = row.cloneNode(true);
        copy.querySelectorAll('form, button, a, input, select, textarea, [aria-hidden="true"]').forEach((control) => control.remove());
        const extraText = row.dataset.searchText || '';
        return `${copy.textContent || ''} ${selectedLabels.join(' ')} ${extraText}`.normalize('NFKC').toLocaleLowerCase();
    };
    const filterLiveRows = () => {
        const query = liveSearchInput.value.trim().normalize('NFKC').toLocaleLowerCase();
        let matches = 0;
        for (const row of recordRows) {
            const visible = query === '' || searchableText(row).includes(query);
            row.hidden = !visible;
            if (visible) matches += 1;
        }
        for (const row of originalEmptyRows) row.hidden = query !== '';
        noResultsRow.hidden = query === '' || matches > 0;
        if (resultCount) {
            resultCount.textContent = query === ''
                ? `${recordRows.length} ${recordRows.length === 1 ? 'record' : 'records'}`
                : `${matches} of ${recordRows.length} ${recordRows.length === 1 ? 'record' : 'records'} match`;
        }
    };
    liveSearchInput.addEventListener('input', filterLiveRows);
    filterLiveRows();
}

document.addEventListener('submit', function (event) {
    const form = event.target.closest('form[data-confirm]');
    if (!form) return;
    if (!window.confirm(form.dataset.confirm || 'Continue with this change?')) {
        event.preventDefault();
    }
});
