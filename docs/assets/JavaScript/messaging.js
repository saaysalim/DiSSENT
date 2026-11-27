/*
 * Messaging page: switch contacts without reloading, send messages and files,
 * and poll for new messages and secure-transfer status.
 * Loaded by Pages/messaging.php (kept out of the page for the Content-Security-Policy).
 */
(function () {
    const chatArea = document.getElementById('chatArea');
    const contactList = document.getElementById('contactListItems');
    const search = document.getElementById('contactSearch');
    const container = document.getElementById('messageContainer');

    // Set by messaging.php (data-receiver-id on #chatArea).
    let receiverId = Number(chatArea.dataset.receiverId) || 0;
    let version = 0;
    let navigation = null;
    let loading = false;
    let polling = false;
    let sending = false;

    const notice = document.createElement('p');
    notice.setAttribute('role', 'status');
    search.insertAdjacentElement('afterend', notice);

    function messageBox() {
        return chatArea.querySelector('#messagesBox');
    }

    function scrollMessages() {
        const box = messageBox();
        if (box) box.scrollTop = box.scrollHeight;
    }

    function relativeTime(value) {
        const date = new Date(value.replace(' ', 'T') + 'Z');
        if (Number.isNaN(date.getTime())) return value;

        const seconds = Math.max(0, (Date.now() - date.getTime()) / 1000);

        if (seconds < 60) return 'Just now';
        if (seconds < 3600) return Math.floor(seconds / 60) + ' min ago';
        if (seconds < 86400) return Math.floor(seconds / 3600) + ' hr ago';
        if (seconds < 604800) return Math.floor(seconds / 86400) + ' day(s) ago';

        return date.toLocaleString('en-GB');
    }

    function updateTimes() {
        chatArea.querySelectorAll('.msg-time').forEach(function (item) {
            item.textContent = relativeTime(item.dataset.time);
        });
    }

    function markSelectedContact() {
        contactList.querySelectorAll('.contact-item').forEach(function (link) {
            const id = Number(new URL(link.href).searchParams.get('user'));
            link.classList.toggle('active', id === receiverId);
        });

        // Phones: show the chat screen while a conversation is open, otherwise the list.
        if (container) container.classList.toggle('has-chat', receiverId > 0);
    }

    // A new message request, or someone accepted ours: offer to show it.
    const startRequests = Number(container ? container.dataset.requests : 0) || 0;
    const startContacts = Number(container ? container.dataset.contacts : 0) || 0;
    let changeNotice = null;

    function noticeNewContacts(result) {
        if (changeNotice || !result) return;

        const moreRequests = Number(result.requests) > startRequests;
        const moreContacts = Number(result.contacts) > startContacts;
        if (!moreRequests && !moreContacts) return;

        changeNotice = document.createElement('p');
        changeNotice.className = 'contacts-changed';
        changeNotice.setAttribute('role', 'status');
        changeNotice.appendChild(document.createTextNode(
            moreRequests ? 'You have a new message request. ' : 'Someone accepted your request. '
        ));
        const link = document.createElement('a');
        link.href = 'messaging.php';
        link.textContent = 'Show it';
        changeNotice.appendChild(link);
        contactList.insertAdjacentElement('beforebegin', changeNotice);
    }

    // Unread numbers next to each contact and on "Messaging" in the menu.
    function updateUnread(counts, total) {
        if (!counts || typeof counts !== 'object') return;

        contactList.querySelectorAll('.contact-item').forEach(function (link) {
            const badge = link.querySelector('.contact-unread');
            if (!badge) return;

            const n = Number(counts[link.dataset.id] || 0);
            badge.hidden = n <= 0;
            if (badge.firstChild) badge.firstChild.nodeValue = String(Math.min(n, 99));
        });

        const navLink = document.querySelector('#primaryNavigation a[href="messaging.php"]');
        if (!navLink) return;

        let navBadge = document.getElementById('navUnreadBadge');
        const sum = Number(total) || 0;

        if (sum <= 0) {
            if (navBadge) navBadge.remove();
            return;
        }
        if (!navBadge) {
            navBadge = document.createElement('span');
            navBadge.className = 'nav-badge';
            navBadge.id = 'navUnreadBadge';
            navBadge.appendChild(document.createTextNode(''));
            const hiddenText = document.createElement('span');
            hiddenText.className = 'visually-hidden';
            hiddenText.textContent = ' unread';
            navBadge.appendChild(hiddenText);
            navLink.appendChild(navBadge);
        }
        navBadge.firstChild.nodeValue = String(Math.min(sum, 99));
    }

    // "New message": a name is not an email address. Show the message next to
    // the box and do not send; the server checks again (backend/contacts.php).
    const requestForm = document.getElementById('newRequestForm');
    if (requestForm) {
        const emailBox = document.getElementById('requestEmail');
        const emailError = document.getElementById('requestEmailError');
        const looksLikeEmail = function (value) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) && emailBox.checkValidity();
        };
        const showEmailError = function (show) {
            emailError.hidden = !show;
            if (show) emailBox.setAttribute('aria-invalid', 'true');
            else emailBox.removeAttribute('aria-invalid');
        };

        requestForm.addEventListener('submit', function (event) {
            emailBox.value = emailBox.value.trim();
            if (!looksLikeEmail(emailBox.value)) {
                event.preventDefault();
                showEmailError(true);
                emailBox.focus();
            }
        });
        emailBox.addEventListener('input', function () {
            if (!emailError.hidden && looksLikeEmail(emailBox.value.trim())) showEmailError(false);
        });
    }

    // Keep contact searching working without reloading.
    search.addEventListener('input', function () {
        const query = search.value.trim().toLowerCase();
        let visible = 0;

        contactList.querySelectorAll('.contact-item').forEach(function (item) {
            const matches =
                item.dataset.name.includes(query) ||
                item.dataset.role.includes(query);

            item.hidden = !matches;
            if (matches) visible++;
        });

        document.getElementById('noContactsFound').hidden = visible > 0;
    });

    // Fetch the selected conversation, then replace only the chat contents.
    async function openConversation(url, addHistory) {
        if (navigation) navigation.abort();

        navigation = new AbortController();
        const thisVersion = ++version;

        loading = true;
        notice.textContent = 'Loading conversation…';
        chatArea.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url.href, {
                signal: navigation.signal,
                cache: 'no-store'
            });

            if (!response.ok) throw new Error('Conversation request failed.');

            const html = await response.text();
            if (thisVersion !== version) return;

            const page = new DOMParser().parseFromString(html, 'text/html');
            const replacement = page.getElementById('chatArea');

            if (!replacement) {
                throw new Error('Could not load the conversation. Your login may have expired.');
            }

            chatArea.innerHTML = replacement.innerHTML;

            const receiverField = chatArea.querySelector('[name="receiver_id"]');
            receiverId = receiverField ? Number(receiverField.value) : 0;

            if (addHistory) {
                history.pushState(null, '', url.pathname + url.search + (receiverId > 0 ? '#chatArea' : ''));
            }

            // The opened conversation is now read.
            const opened = contactList.querySelector('.contact-item[data-id="' + receiverId + '"] .contact-unread');
            if (opened) opened.hidden = true;

            markSelectedContact();
            updateTimes();
            scrollMessages();
            notice.textContent = '';
        } catch (error) {
            if (error.name !== 'AbortError' && thisVersion === version) {
                notice.textContent = 'Could not switch contacts. Please try again.';
                console.error(error);
            }
        } finally {
            if (thisVersion === version) {
                loading = false;
                chatArea.removeAttribute('aria-busy');
                pollSecureStatus();
            }
        }
    }

    // Phones: "All contacts" goes back to the list without reloading.
    chatArea.addEventListener('click', function (event) {
        const back = event.target.closest('a.chat-back');

        if (!back || event.button !== 0 || event.ctrlKey ||
            event.metaKey || event.shiftKey || event.altKey) return;

        event.preventDefault();
        openConversation(new URL(back.href), true).then(function () {
            if (search) search.focus({ preventScroll: true });
        });
    });

    contactList.addEventListener('click', function (event) {
        const link = event.target.closest('a.contact-item');

        // Preserve Ctrl-click and other normal browser shortcuts.
        if (!link || event.button !== 0 || event.ctrlKey ||
            event.metaKey || event.shiftKey || event.altKey) return;

        event.preventDefault();
        openConversation(new URL(link.href), true);
    });

    // Support the browser's Back and Forward buttons.
    window.addEventListener('popstate', function () {
        openConversation(new URL(window.location.href), false);
    });

    // Delegation keeps these handlers working after switching contacts.
    chatArea.addEventListener('keydown', function (event) {
        if (!event.target.matches('textarea[name="message"]')) return;
        if (event.isComposing || event.keyCode === 229) return;

        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();

            if (!event.repeat && !loading) {
                event.target.form.requestSubmit();
            }
        }
    });

    chatArea.addEventListener('submit', async function (event) {
        const form = event.target;
        if (!form.matches('#messageForm')) return;
        event.preventDefault();

        if (loading || sending || receiverId <= 0) return;

        const textarea = form.querySelector('textarea');
        const fileInput = form.querySelector('input[type="file"]');
        const file = fileInput && fileInput.files[0];

        if (!textarea.value.trim() && !file) {
            textarea.setCustomValidity('Please type a message or attach a file.');
            textarea.reportValidity();
            return;
        }
        if (file && file.size > 5 * 1024 * 1024) {
            showFormNotice('The file is too large. The limit is 5 MB.');
            return;
        }

        const body = new FormData(form);
        const sentTo = receiverId;
        const thisVersion = ++version;
        const button = form.querySelector('button[type="submit"]');
        const hadFocus = form.contains(document.activeElement);

        sending = true;
        textarea.readOnly = true;
        button.disabled = true;
        form.setAttribute('aria-busy', 'true');
        showFormNotice('');
        notice.textContent = 'Sending message…';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'Accept': 'application/json' }
            });
            const type = response.headers.get('Content-Type') || '';
            if (!type.includes('application/json')) {
                throw new Error('Unexpected reply from the server.');
            }

            const result = await response.json();

            if (!result.ok) {
                notice.textContent = '';
                showFormNotice(result.error || 'Your message could not be sent.');
                return;
            }

            notice.textContent = result.held ? 'Message saved. It is waiting for review.' : '';
            if (thisVersion !== version || receiverId !== sentTo) return;

            applyMessages([{ id: result.id, html: result.html }]);
            form.reset();
            textarea.setCustomValidity('');
            updateTimes();
            scrollMessages();
            if (hadFocus) textarea.focus({ preventScroll: true });
        } catch (error) {
            notice.textContent = '';
            showFormNotice('Could not confirm sending. Check the conversation before trying again; the message may already have been saved.');
            console.error('Send confirmation failed:', error);
        } finally {
            sending = false;
            if (form.isConnected) {
                textarea.readOnly = false;
                button.disabled = false;
                form.removeAttribute('aria-busy');
            }
            pollMessages();
            pollSecureStatus();
        }
    });

    chatArea.addEventListener('input', function (event) {
        const textarea = chatArea.querySelector('textarea[name="message"]');
        if (textarea) textarea.setCustomValidity('');
    });

    async function pollSecureStatus() {
        if (loading) return;

        const thisVersion = version;

        chatArea.querySelectorAll('[data-envelope-id]').forEach(async function (item) {
            if (item.dataset.secureResolved === 'true' ||
                item.dataset.checking === 'true') return;

            item.dataset.checking = 'true';

            try {
                const response = await fetch(
                    'message_status.php?envelope_id=' +
                    encodeURIComponent(item.dataset.envelopeId),
                    { cache: 'no-store' }
                );

                if (!response.ok) return;
                const result = await response.json();

                if (thisVersion !== version || !item.isConnected) return;

                const badge = item.querySelector('.secure-badge');
                if (!badge) return;

                if (result.status === 'verified') {
                    badge.textContent = 'Secure transfer: verified ✓';
                    badge.title = 'The second DiSSENT system checked the signature and decrypted the copy.';
                    badge.classList.add('secure-verified');
                    item.dataset.secureResolved = 'true';
                } else if (result.status === 'failed') {
                    badge.textContent = 'Secure transfer: failed';
                    badge.title = 'The second DiSSENT system could not check the encrypted copy. The message itself was delivered on DiSSENT.';
                    badge.classList.add('secure-failed');
                    item.dataset.secureResolved = 'true';
                }
            } catch (error) {
                console.error('Status check failed:', error);
            } finally {
                delete item.dataset.checking;
            }
        });
    }

    // Message next to the form (errors only).
    function showFormNotice(text) {
        const form = chatArea.querySelector('#messageForm');
        let box = chatArea.querySelector('.message-form-notice');

        if (!text) {
            if (box) box.remove();
            return;
        }
        if (!box) {
            box = document.createElement('div');
            box.className = 'notice notice--error message-form-notice';
            box.setAttribute('role', 'alert');
            form.insertAdjacentElement('beforebegin', box);
        }
        box.textContent = text;
    }

    // Adds new bubbles and redraws changed ones. The HTML is escaped by PHP (message_html()).
    function applyMessages(items) {
        const box = messageBox();
        if (!box) return;

        items.forEach(function (item) {
            const template = document.createElement('template');
            template.innerHTML = item.html || '';
            const bubble = template.content.firstElementChild;
            if (!bubble) return;

            const existing = box.querySelector('[data-id="' + Number(item.id) + '"]');

            if (existing) {
                if (existing.dataset.rev !== bubble.dataset.rev) existing.replaceWith(bubble);
                return;
            }

            const placeholder = box.querySelector('.no-message');
            if (placeholder) placeholder.remove();
            box.appendChild(bubble);
        });
    }

    // No open conversation: only refresh the unread numbers.
    async function pollUnread() {
        try {
            const response = await fetch('get_new_messages.php?receiver_id=0', { cache: 'no-store' });
            if (!response.ok) return;
            const result = await response.json();
            if (receiverId <= 0) updateUnread(result.unread, result.unread_total);
            noticeNewContacts(result);
        } catch (error) {
            console.error('Unread check failed:', error);
        }
    }

    async function pollMessages() {
        if (!loading && !sending && !polling && receiverId <= 0) {
            polling = true;
            await pollUnread();
            polling = false;
            return;
        }
        if (loading || sending || polling || receiverId <= 0) return;

        polling = true;
        const thisVersion = version;
        const box = messageBox();

        try {
            const response = await fetch(
                'get_new_messages.php?receiver_id=' + receiverId +
                '&since=' + encodeURIComponent(box.dataset.cursor || ''),
                { cache: 'no-store' }
            );

            if (!response.ok) return;
            const result = await response.json();

            // Ignore replies belonging to a previously selected contact.
            if (thisVersion !== version || loading || !Array.isArray(result.items)) return;

            const nearBottom =
                box.scrollHeight - box.scrollTop - box.clientHeight < 100;

            applyMessages(result.items);
            box.dataset.cursor = result.cursor;
            updateTimes();
            updateUnread(result.unread, result.unread_total);
            noticeNewContacts(result);

            if (nearBottom) scrollMessages();
            pollSecureStatus();
        } catch (error) {
            console.error('Message check failed:', error);
        } finally {
            polling = false;
        }
    }

    updateTimes();
    scrollMessages();
    pollSecureStatus();
    markSelectedContact();

    // Position the conversation on the initial page load.
    if (receiverId > 0) {
        requestAnimationFrame(function () {
            chatArea.scrollIntoView({ behavior: 'auto', block: 'start' });
        });
    }

    setInterval(updateTimes, 30000);
    setInterval(pollMessages, 4000);
    setInterval(pollSecureStatus, 4000);
})();
