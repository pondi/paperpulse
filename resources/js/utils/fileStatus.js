export const fileStatusLabels = { pending: 'Queued', processing: 'Processing', completed: 'Ready', failed: 'Failed', needs_review: 'Needs review' };

export const fileStatusLabel = file => file.status === 'needs_review' && file.review?.reason === 'processing_limit'
    ? 'Blocked: processing limit'
    : fileStatusLabels[file.status] || file.status;
