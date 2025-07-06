(function(){
    const widgetContainer = document.getElementById('renewals-widget');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    async function init() {
        if (!widgetContainer) return;
        widgetContainer.innerHTML = '<div class="widget-loading">Loading...</div>';
        await fetchUpcomingRenewals();
        widgetContainer.addEventListener('click', onWidgetClick);
    }

    async function fetchUpcomingRenewals() {
        try {
            const response = await fetch('/api/renewals/upcoming', {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            });
            if (!response.ok) throw new Error('Network response was not ok');
            const data = await response.json();
            renderWidget(data);
        } catch (error) {
            widgetContainer.innerHTML = '<div class="widget-error">Failed to load renewals.</div>';
            console.error('fetchUpcomingRenewals error:', error);
        }
    }

    function renderWidget(renewals) {
        if (!Array.isArray(renewals) || renewals.length === 0) {
            widgetContainer.innerHTML = '<div class="widget-empty">No upcoming renewals.</div>';
            return;
        }
        const list = document.createElement('ul');
        list.className = 'renewals-list';
        renewals.forEach(item => {
            const li = document.createElement('li');
            li.className = 'renewal-item';
            li.dataset.id = item.id;
            const name = escapeHtml(item.name);
            const dateText = escapeHtml(formatDate(item.due_date));
            li.innerHTML = ''
                + '<div class="renewal-info">'
                +   '<span class="renewal-name">' + name + '</span>'
                +   '<span class="renewal-date">' + dateText + '</span>'
                + '</div>'
                + '<div class="renewal-actions">'
                +   '<button class="snooze-btn" data-action="snooze">Snooze</button>'
                +   '<button class="reschedule-btn" data-action="reschedule">Reschedule</button>'
                + '</div>';
            list.appendChild(li);
        });
        widgetContainer.innerHTML = '';
        widgetContainer.appendChild(list);
    }

    function onWidgetClick(event) {
        const btn = event.target.closest('button[data-action]');
        if (!btn) return;
        const action = btn.getAttribute('data-action');
        const itemEl = btn.closest('.renewal-item');
        if (!itemEl) return;
        const id = parseInt(itemEl.dataset.id, 10);
        if (action === 'snooze') {
            snoozeRenewal(id);
        } else if (action === 'reschedule') {
            rescheduleRenewal(id);
        }
    }

    async function snoozeRenewal(id) {
        if (!confirm('Snooze this renewal by 7 days?')) return;
        try {
            const response = await fetch(`/api/renewals/${id}/snooze`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ days: 7 })
            });
            if (!response.ok) throw new Error('Network response was not ok');
            await fetchUpcomingRenewals();
        } catch (error) {
            alert('Failed to snooze renewal.');
            console.error('snoozeRenewal error:', error);
        }
    }

    async function rescheduleRenewal(id) {
        const newDate = prompt('Enter new renewal date (YYYY-MM-DD):');
        if (!newDate) return;
        if (!/^\d{4}-\d{2}-\d{2}$/.test(newDate)) {
            alert('Invalid date format.');
            return;
        }
        const parsedDate = new Date(newDate);
        if (isNaN(parsedDate) || parsedDate.toISOString().slice(0,10) !== newDate) {
            alert('Invalid date.');
            return;
        }
        try {
            const response = await fetch(`/api/renewals/${id}/reschedule`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ date: newDate })
            });
            if (!response.ok) throw new Error('Network response was not ok');
            await fetchUpcomingRenewals();
        } catch (error) {
            alert('Failed to reschedule renewal.');
            console.error('rescheduleRenewal error:', error);
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDate(dateStr) {
        const date = new Date(dateStr);
        if (isNaN(date)) return dateStr;
        return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }

    document.addEventListener('DOMContentLoaded', init);
})();