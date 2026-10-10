/**
 * VoltCraft 3D - Main Application JavaScript
 * Handles themes, mobile navigation, counter animations, toasts, modals, and interactive calculations
 */

(function () {
  'use strict';

  // 1. Theme Toggle (Dark / Light)
  const savedTheme = localStorage.getItem('volt_theme') || 'dark';
  document.documentElement.setAttribute('data-theme', savedTheme);

  document.addEventListener('DOMContentLoaded', () => {
    const themeBtns = document.querySelectorAll('.theme-toggle-btn');
    themeBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('volt_theme', newTheme);
      });
    });

    // 2. Mobile Nav Toggle
    const mobileBtn = document.querySelector('.mobile-menu-btn');
    const navLinks = document.querySelector('.nav-links');
    if (mobileBtn && navLinks) {
      mobileBtn.addEventListener('click', () => {
        navLinks.classList.toggle('active');
      });
    }

    // 3. User Dropdown Menu
    const userMenuBtn = document.querySelector('.user-profile-btn');
    const userDropdown = document.querySelector('.user-dropdown');
    if (userMenuBtn && userDropdown) {
      userMenuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        userDropdown.classList.toggle('show');
      });

      document.addEventListener('click', (e) => {
        if (!userDropdown.contains(e.target) && !userMenuBtn.contains(e.target)) {
          userDropdown.classList.remove('show');
        }
      });
    }

    // 4. Sidebar Toggle on Dashboards
    const sidebarToggle = document.querySelector('.sidebar-toggle-btn');
    const sidebar = document.querySelector('.dashboard-sidebar');
    if (sidebarToggle && sidebar) {
      sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('open');
      });
    }

    // 5. Animated Statistic Counters (IntersectionObserver)
    const counters = document.querySelectorAll('[data-counter]');
    if (counters.length && 'IntersectionObserver' in window) {
      const counterObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            const el = entry.target;
            const target = parseInt(el.getAttribute('data-counter'), 10);
            const prefix = el.getAttribute('data-prefix') || '';
            const suffix = el.getAttribute('data-suffix') || '';
            const duration = 1500;
            const stepTime = 20;
            const totalSteps = duration / stepTime;
            const stepIncrement = target / totalSteps;
            let current = 0;

            const timer = setInterval(() => {
              current += stepIncrement;
              if (current >= target) {
                el.innerText = prefix + target.toLocaleString() + suffix;
                clearInterval(timer);
              } else {
                el.innerText = prefix + Math.floor(current).toLocaleString() + suffix;
              }
            }, stepTime);

            observer.unobserve(el);
          }
        });
      }, { threshold: 0.2 });

      counters.forEach(c => counterObserver.observe(c));
    }

    // 6. Generic Modals
    document.querySelectorAll('[data-modal-target]').forEach(trigger => {
      trigger.addEventListener('click', (e) => {
        e.preventDefault();
        const targetId = trigger.getAttribute('data-modal-target');
        const modal = document.getElementById(targetId);
        if (modal) {
          modal.classList.add('show');
        }
      });
    });

    document.querySelectorAll('[data-modal-close]').forEach(closeBtn => {
      closeBtn.addEventListener('click', () => {
        const modal = closeBtn.closest('.modal-backdrop');
        if (modal) {
          modal.classList.remove('show');
        }
      });
    });

    window.addEventListener('click', (e) => {
      if (e.target.classList.contains('modal-backdrop')) {
        e.target.classList.remove('show');
      }
    });

    // 7. Auto-Calculate 20% Advance Breakdown on Booking & Request Forms
    const serviceSelect = document.getElementById('service_select');
    const totalInput = document.getElementById('estimated_amount');
    const advanceDisplay = document.getElementById('advance_preview_val');
    const remainingDisplay = document.getElementById('remaining_preview_val');
    const totalDisplay = document.getElementById('total_preview_val');

    function updateBreakdown(val) {
      const total = parseFloat(val) || 0;
      const advance = total * 0.20;
      const remaining = total * 0.80;

      if (totalDisplay) totalDisplay.innerText = '₹ ' + total.toFixed(2);
      if (advanceDisplay) advanceDisplay.innerText = '₹ ' + advance.toFixed(2);
      if (remainingDisplay) remainingDisplay.innerText = '₹ ' + remaining.toFixed(2);
    }

    if (serviceSelect) {
      serviceSelect.addEventListener('change', () => {
        const selectedOpt = serviceSelect.options[serviceSelect.selectedIndex];
        const price = selectedOpt.getAttribute('data-price');
        if (price && totalInput) {
          totalInput.value = price;
          updateBreakdown(price);
        }
      });
      // Initial trigger if already selected
      if (serviceSelect.value) {
        const opt = serviceSelect.options[serviceSelect.selectedIndex];
        const p = opt.getAttribute('data-price');
        if (p) updateBreakdown(p);
      }
    }

    if (totalInput) {
      totalInput.addEventListener('input', () => {
        updateBreakdown(totalInput.value);
      });
    }
  });

  // 8. Global Toast Notification Helper
  window.showToast = function (message, type = 'info') {
    let container = document.querySelector('.toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    let iconSvg = '⚡';
    if (type === 'success') iconSvg = '✓';
    if (type === 'danger') iconSvg = '✕';
    if (type === 'warning') iconSvg = '⚠';

    toast.innerHTML = `
      <span style="font-weight:bold;color:var(--primary);">${iconSvg}</span>
      <span style="font-size:0.9rem;">${message}</span>
    `;

    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateX(100%)';
      toast.style.transition = 'all 0.3s ease';
      setTimeout(() => toast.remove(), 300);
    }, 4000);
  };

})();
