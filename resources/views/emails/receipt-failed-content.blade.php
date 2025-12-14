<h1>Receipt Processing Failed</h1>

<p>We encountered an error while processing your receipt.</p>

<div class="accent-box">
    <p style="margin: 0;"><strong>Error:</strong> {{ $error_message }}</p>
</div>

<p>Please try uploading the receipt again or contact support if the issue persists.</p>

<div class="text-center">
    <a href="{{ $upload_url }}" class="btn btn-accent">Upload New Receipt</a>
</div>
