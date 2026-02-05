<script>
    (function() {
        if (window.nsOutstandingTicketsActionListenerRegistered) {
            return;
        }
        window.nsOutstandingTicketsActionListenerRegistered = true;

        const subscribe = () => {
            if (typeof nsEvent === 'undefined') {
                return;
            }
            nsEvent.subject().subscribe(event => {
                if (event.identifier !== 'ns-table-row-action') {
                    return;
                }
                const action = event.value?.action || {};
                const row = event.value?.row || null;
                const component = event.value?.component || null;
                if (action.type === 'POPUP' && component && (action.identifier === 'pay_from_wallet' || action.identifier === 'pay_order')) {
                    component.triggerPopup(action, row);
                }
            });
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', subscribe);
        } else {
            subscribe();
        }
    })();
</script>
