/**
 * VoltCraft 3D - API Client
 * Clean AJAX communication with PHP REST endpoints
 */

const ApiClient = {
  /**
   * Universal fetch wrapper
   */
  async request(endpoint, options = {}) {
    const defaultHeaders = {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    };

    if (!(options.body instanceof FormData)) {
      defaultHeaders['Content-Type'] = 'application/json';
    }

    const config = {
      ...options,
      headers: {
        ...defaultHeaders,
        ...(options.headers || {})
      }
    };

    try {
      const response = await fetch(endpoint, config);
      const data = await response.json();
      return data;
    } catch (error) {
      console.error('API Request failed:', error);
      return {
        status: 'error',
        data: { message: 'Network or server communication error.' }
      };
    }
  },

  /**
   * Search / Filter Engineers
   */
  async filterEngineers(params) {
    const query = new URLSearchParams(params).toString();
    return this.request(`api/engineer.php?action=filter&${query}`);
  },

  /**
   * Accept / Reject Service Request
   */
  async respondToRequest(requestId, action, reason = '') {
    return this.request('api/booking.php?action=respond_request', {
      method: 'POST',
      body: JSON.stringify({ request_id: requestId, action: action, reason: reason })
    });
  },

  /**
   * Update Booking Status (e.g., IN_PROGRESS, COMPLETED)
   */
  async updateBookingStatus(bookingId, status, notes = '') {
    return this.request('api/booking.php?action=update_status', {
      method: 'POST',
      body: JSON.stringify({ booking_id: bookingId, status: status, notes: notes })
    });
  },

  /**
   * Process 20% Advance Payment
   */
  async processPayment(payload) {
    return this.request('api/payment.php?action=pay_advance', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  },

  /**
   * Mark Notification as Read
   */
  async markNotificationRead(notifId) {
    return this.request('api/notification.php?action=mark_read', {
      method: 'POST',
      body: JSON.stringify({ id: notifId })
    });
  },

  /**
   * Admin: Authorize OTP Generation
   */
  async authorizeOtp(bookingId, expiryMinutes = 15) {
    return this.request('api/otp.php?action=authorize_otp', {
      method: 'POST',
      body: JSON.stringify({ booking_id: bookingId, expiry_minutes: expiryMinutes })
    });
  },

  /**
   * Admin: Reject OTP Request
   */
  async rejectOtp(bookingId, reason = '') {
    return this.request('api/otp.php?action=reject_otp', {
      method: 'POST',
      body: JSON.stringify({ booking_id: bookingId, reason: reason })
    });
  },

  /**
   * Admin: Regenerate OTP
   */
  async regenerateOtp(bookingId, expiryMinutes = 15) {
    return this.request('api/otp.php?action=regenerate_otp', {
      method: 'POST',
      body: JSON.stringify({ booking_id: bookingId, expiry_minutes: expiryMinutes })
    });
  },

  /**
   * Engineer: Verify Customer OTP
   */
  async verifyOtp(bookingId, otp) {
    return this.request('api/otp.php?action=verify_otp', {
      method: 'POST',
      body: JSON.stringify({ booking_id: bookingId, otp: otp })
    });
  },

  /**
   * Admin: Fetch Booking OTP Audit Logs
   */
  async getOtpAuditLogs(bookingId) {
    return this.request(`api/otp.php?action=get_audit_logs&booking_id=${bookingId}`);
  },

  /**
   * Admin: Fetch OTP Verification Summary Stats
   */
  async getOtpStats() {
    return this.request('api/otp.php?action=get_stats');
  }
};

window.ApiClient = ApiClient;
