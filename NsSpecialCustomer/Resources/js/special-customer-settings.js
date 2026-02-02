/**
 * Special Customer Settings Module
 *
 * Handles the settings form functionality for the Special Customer module.
 */

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', async function() {
    await loadCurrentSettings();

    const form = document.getElementById('settings-form');
    if (form) {
        form.addEventListener('submit', saveSettings);
    }
});

/**
 * Load current settings from the API
 */
async function loadCurrentSettings() {
    try {
        const response = await fetch('/api/special-customer/config');
        const result = await response.json();

        if (result.status === 'success') {
            const config = result.data;
            const discountInput = document.querySelector('input[name="discount_percentage"]');
            const cashbackInput = document.querySelector('input[name="cashback_percentage"]');
            const stackableInput = document.querySelector('input[name="apply_discount_stackable"]');

            if (discountInput) {
                discountInput.value = config.discountPercentage;
            }
            if (cashbackInput) {
                cashbackInput.value = config.cashbackPercentage;
            }
            if (stackableInput) {
                stackableInput.checked = config.applyDiscountStackable;
            }
        }
    } catch (error) {
        console.error('Failed to load settings:', error);
        showNotification('Failed to load settings', 'error');
    }
}

/**
 * Save settings to the API
 *
 * @param {Event} e The submit event
 */
async function saveSettings(e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);
    const data = {
        discount_percentage: parseFloat(formData.get('discount_percentage')),
        cashback_percentage: parseFloat(formData.get('cashback_percentage')),
        apply_discount_stackable: formData.has('apply_discount_stackable')
    };

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        const headers = {
            'Content-Type': 'application/json',
        };

        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken.getAttribute('content');
        }

        const response = await fetch('/api/special-customer/settings', {
            method: 'POST',
            headers: headers,
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.status === 'success') {
            showNotification('Settings updated successfully', 'success');
        } else {
            showNotification(result.message || 'Failed to update settings', 'error');
        }
    } catch (error) {
        console.error('Failed to update settings:', error);
        showNotification('Failed to update settings', 'error');
    }
}

/**
 * Reset settings to default values
 */
function resetToDefaults() {
    if (confirm('Are you sure you want to reset all settings to defaults?')) {
        const discountInput = document.querySelector('input[name="discount_percentage"]');
        const cashbackInput = document.querySelector('input[name="cashback_percentage"]');
        const stackableInput = document.querySelector('input[name="apply_discount_stackable"]');

        if (discountInput) {
            discountInput.value = '7.00';
        }
        if (cashbackInput) {
            cashbackInput.value = '2.00';
        }
        if (stackableInput) {
            stackableInput.checked = false;
        }
    }
}

/**
 * Show a notification message
 *
 * @param {string} message The message to display
 * @param {string} type The notification type ('success' or 'error')
 */
function showNotification(message, type) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
        type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
    }`;
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="las ${type === 'success' ? 'la-check-circle' : 'la-exclamation-circle'} mr-2"></i>
            <span>${message}</span>
        </div>
    `;

    document.body.appendChild(notification);

    // Remove after 3 seconds
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Make resetToDefaults available globally for onclick handlers
window.resetToDefaults = resetToDefaults;
