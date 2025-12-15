<h1>Duplicate File Detected</h1>

<p>We noticed that the file you uploaded is a duplicate of one that already exists in your account.</p>

<div class="accent-box">
    <p style="margin: 0 0 8px 0;"><strong>Uploaded file:</strong> {{ $uploaded_file_name }}</p>
    <p style="margin: 0 0 8px 0;"><strong>Existing file:</strong> {{ $existing_file_name }}</p>
    <p style="margin: 0;"><strong>Type:</strong> {{ ucfirst($file_type) }}</p>
</div>

<p>To prevent duplicates, the file was not uploaded. The original file remains in your account.</p>

<div class="text-center">
    <a href="{{ $file_url }}" class="btn btn-accent">{{ $file_link_text }}</a>
</div>

<p>If you believe this is an error, please contact support.</p>

<p>Thank you for using PaperPulse!</p>
