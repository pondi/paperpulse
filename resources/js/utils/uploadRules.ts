export type UploadProvider = 'gemini' | 'textract+openai';
export type UploadFileType = 'receipt' | 'document';

export interface UploadRulesConfig {
    provider?: UploadProvider;
    maxFileSizeMb?: {
        receipt?: number;
        document?: number;
    };
}

const DEFAULT_MAX_MB: Record<UploadFileType, number> = {
    receipt: 10,
    document: 50,
};

export function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

export function resolveUploadRules(
    config: UploadRulesConfig | null | undefined,
    fileType: UploadFileType,
    maxSizeBytesOverride?: number
): {
    provider: UploadProvider;
    maxSizeMb: number;
    maxSizeBytes: number;
    oversizeMessage: string;
} {
    const provider = (config?.provider ?? 'textract+openai') as UploadProvider;
    let maxSizeMb = config?.maxFileSizeMb?.[fileType] ?? DEFAULT_MAX_MB[fileType];

    if (maxSizeBytesOverride && maxSizeBytesOverride > 0) {
        maxSizeMb = Math.max(1, Math.ceil(maxSizeBytesOverride / (1024 * 1024)));
    }

    const maxSizeBytes = maxSizeBytesOverride && maxSizeBytesOverride > 0
        ? maxSizeBytesOverride
        : maxSizeMb * 1024 * 1024;

    const oversizeMessage = provider === 'gemini'
        ? `Gemini processing supports files up to ${maxSizeMb}MB. Please upload a smaller file or switch providers.`
        : `File size exceeds maximum limit of ${maxSizeMb}MB for ${fileType}s`;

    return {
        provider,
        maxSizeMb,
        maxSizeBytes,
        oversizeMessage,
    };
}
