/*
 * DiSSENT login interactions.
 *
 * - Show/Hide password.
 * - Restore masking when leaving the page.
 * - Display submission feedback.
 * - Prevent accidental repeated submissions.
 *
 * Authentication still happens in PHP.
 * This script does not store or log passwords.
 */

(function () {
    'use strict';

    const root = document.getElementById('dissent-login');

    if (!root) {
        return;
    }

    const form = root.querySelector('#dissent-login-form');
    const password = root.querySelector('#login-password');
    const toggle = root.querySelector('#login-password-toggle');
    const submit = root.querySelector('#login-submit');
    const wrapper = root.querySelector('.login-password-control');

    if (!form || !password || !toggle || !submit || !wrapper) {
        return;
    }

    let submitting = false;

    function setVisible(visible) {
        password.type = visible ? 'text' : 'password';

        toggle.textContent = visible ? 'Hide' : 'Show';

        toggle.setAttribute(
            'aria-label',
            visible ? 'Hide password' : 'Show password'
        );
    }

    function resetInterface() {
        setVisible(false);

        submitting = false;

        submit.disabled = false;
        submit.textContent = 'Log in';

        form.removeAttribute('aria-busy');
    }

    /*
     * type="button" prevents Show/Hide from submitting the form.
     */
    toggle.addEventListener('click', function () {
        setVisible(password.type === 'password');
    });

    /*
     * The form still performs a normal POST to login.php.
     * Browser validation runs before this submit event.
     */
    form.addEventListener('submit', function (event) {
        if (event.defaultPrevented) {
            return;
        }

        if (submitting) {
            event.preventDefault();
            return;
        }

        setVisible(false);

        submitting = true;

        submit.disabled = true;
        submit.textContent = 'Logging in...';

        form.setAttribute('aria-busy', 'true');
    });

    /*
     * Restore the controls after reset or browser back navigation.
     */
    form.addEventListener('reset', resetInterface);

    window.addEventListener('pageshow', resetInterface);

    window.addEventListener('pagehide', function () {
        setVisible(false);
    });

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            setVisible(false);
        }
    });

    /*
     * Only display the Show/Hide button after its handler is ready.
     */
    wrapper.setAttribute('data-ready', 'true');
    toggle.hidden = false;

    resetInterface();

    /*
     * Bring a server-side error message into keyboard focus.
     */
    const message = root.querySelector('#login-message');

    if (message) {
        message.focus();
    }
}());