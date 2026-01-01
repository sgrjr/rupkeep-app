# SMS Gateway Email Delivery Troubleshooting

## Understanding the 452 Error

When you see an error like:

```
452 4.1.0 <bounces-303709042-502820442@gz.d.sender-sib.com> server temporarily unavailable AUP#MXRT
```

This indicates that:

1. **Your application successfully sent the email to Brevo** ✅
2. **Brevo successfully accepted the email** ✅
3. **The carrier's email-to-SMS gateway temporarily rejected the message** ⚠️

### What the Error Means

- **Error Code 452**: "Requested mail action aborted: insufficient system storage" or "server temporarily unavailable"
- **AUP#MXRT**: Acceptable Use Policy enforcement by the mail exchange routing system
- **Bounce Address**: `gz.d.sender-sib.com` is Brevo's bounce/deferral notification domain

This is **NOT a problem with your application code** - it's a temporary rejection by the carrier's SMS gateway.

## Common Causes

1. **Rate Limiting**: Sending too many messages in a short time period
2. **Carrier Server Overload**: The carrier's SMS gateway is experiencing high traffic
3. **Server Maintenance**: The carrier's gateway is temporarily down for maintenance
4. **Message Format Issues**: Subject line or content that doesn't meet carrier requirements
5. **Reputation Issues**: The sending domain or IP may be temporarily flagged

## What Happens Next

**Brevo automatically retries** deferred messages. According to Brevo's retry policy:
- Messages are retried at increasing intervals
- Most messages are successfully delivered after retry
- If a message fails after multiple retries, you'll receive a bounce notification

## Recommended Actions

### 1. Monitor Brevo Dashboard

Check your Brevo dashboard regularly for:
- **Statistics > Email Statistics**: View delivery rates, bounces, and deferrals
- **Reports**: Check for patterns in failures (specific carriers, times of day, etc.)
- **Webhooks**: Set up webhooks to receive bounce/deferral notifications automatically

### 2. Optimize Message Format

For better deliverability to SMS gateways:

- **Keep subject lines short**: SMS gateways prefer short subjects (under 50 characters)
- **Keep messages concise**: SMS messages have character limits (typically 160-1600 characters)
- **Use plain text**: Avoid HTML formatting when sending to SMS gateways
- **Avoid special characters**: Some carriers filter messages with excessive special characters

### 3. Rate Limiting

If you're seeing frequent 452 errors:
- **Space out notifications**: Avoid sending multiple messages to the same carrier in quick succession
- **Monitor sending volume**: Check if you're hitting carrier rate limits
- **Use queues**: Ensure notifications are sent asynchronously via queues

### 4. Carrier-Specific Considerations

Different carriers have different policies:

- **Verizon (vtext.com)**: Recommends using Enterprise Messaging Access Gateway (EMAG) for business communications instead of consumer gateways
- **T-Mobile (tmomail.net)**: Generally more lenient, but still subject to rate limits
- **AT&T (txt.att.net)**: Similar rate limiting and filtering policies

### 5. Check Message Content

Some carriers filter messages based on content:
- Avoid spam trigger words
- Don't include URLs in messages (some carriers block them)
- Keep messages professional and concise

## When to Take Action

**No action needed if:**
- Errors are infrequent (< 5% of messages)
- Messages eventually deliver after retry
- Errors occur during known carrier maintenance windows

**Take action if:**
- Error rate exceeds 10% consistently
- Messages never deliver (hard bounces)
- Errors occur at specific times consistently (may indicate rate limiting)
- Errors affect specific carriers only (may need carrier-specific solutions)

## Monitoring and Alerts

Set up monitoring for:
1. **Brevo Dashboard**: Check daily for delivery statistics
2. **Application Logs**: Monitor `storage/logs/laravel.log` for failed delivery attempts
3. **Webhooks** (optional): Set up Brevo webhooks to receive real-time bounce/deferral notifications

## Brevo Webhook Setup (Optional)

To receive automatic notifications of bounces/deferrals:

1. Go to Brevo Dashboard > Settings > Webhooks
2. Add a webhook URL (e.g., `https://yourdomain.com/webhooks/brevo`)
3. Select events: "Hard Bounce", "Soft Bounce", "Deferred"
4. Create a controller to handle webhook events

## Getting Help

If errors persist:

1. **Check Brevo Support**: Brevo support can help diagnose delivery issues
2. **Contact Carrier Support**: For carrier-specific issues, contact the carrier's business messaging support
3. **Review Application Logs**: Check `storage/logs/laravel.log` for detailed error information
4. **Monitor Brevo Statistics**: Use Brevo's analytics to identify patterns

## Additional Resources

- [Brevo Documentation: Email Statistics](https://help.brevo.com/hc/en-us/articles/209467485)
- [Brevo Documentation: Webhooks](https://help.brevo.com/hc/en-us/articles/209467525)
- [Verizon EMAG Information](https://www.verizon.com/business/solutions/enterprise-messaging/)
- [SMTP Error Codes Explained](https://sendgrid.com/en-us/blog/smtp-server-response-codes-explained)
