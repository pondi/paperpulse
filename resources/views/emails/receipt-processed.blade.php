<h1>Receipt Processed Successfully</h1>

<p>Great news! Your receipt has been processed successfully and is now available in your account.</p>

<div class="accent-box">
    <p style="margin: 0 0 8px 0;"><strong>Merchant:</strong> {{ $merchant_name }}</p>
    <p style="margin: 0;"><strong>Amount:</strong> {{ $amount }} {{ $currency }}</p>
</div>

<p>You can now view, search, and manage this receipt from your dashboard.</p>

<div class="text-center">
    <a href="{{ $receipt_url }}" class="btn btn-accent">View Receipt</a>
</div>

<p>Thank you for using PaperPulse!</p>