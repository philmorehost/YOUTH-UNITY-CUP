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
