/** AJAX call to the mock M-Pesa STK Push endpoint. */
function triggerStkPush(baseUrl, bookingId, phone, csrfToken, onStatus, onDone) {
  onStatus('Sending payment request to ' + phone + '…');
  fetch(baseUrl + '/api/mpesa/stkpush.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ bookingId: bookingId, phone: phone, csrf: csrfToken }),
  })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      onStatus(data.success ? 'Payment confirmed — receipt ' + data.receipt
                            : 'Payment failed: ' + (data.message || 'Unknown error'));
      onDone(data);
    })
    .catch(function (err) {
      onStatus('Network error: ' + err.message);
      onDone({ success: false });
    });
}
