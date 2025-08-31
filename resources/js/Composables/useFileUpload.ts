import { ref } from 'vue';

interface FileObject {
    file: File;
    preview: string | null;
    name: string;
    size: string;
    type: string;
}

interface UseFileUploadOptions {
    maxFileSize?: number;
    allowedTypes?: string[];
    onError?: (error: string) => void;
}

export function useFileUpload(options: UseFileUploadOptions = {}) {
    const {
        maxFileSize = 10 * 1024 * 1024, // 10MB default
        allowedTypes = ['application/pdf', 'image/png', 'image/jpeg', 'image/jpg'],
        onError = console.error
    } = options;

    const selectedFiles = ref<FileObject[]>([]);
    const isDragging = ref(false);

    function formatFileSize(bytes: number): string {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function validateFile(file: File): boolean {
        if (file.size > maxFileSize) {
            onError(`File ${file.name} is too large. Maximum size is ${formatFileSize(maxFileSize)}`);
            return false;
        }

        if (!allowedTypes.includes(file.type)) {
            onError(`File ${file.name} has an invalid type. Allowed types are: ${allowedTypes.join(', ')}`);
            return false;
        }

        return true;
    }

    function createFileObject(file: File): FileObject {
        return {
            file,
            preview: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
            name: file.name,
            size: formatFileSize(file.size),
            type: file.type
        };
    }

    function handleFiles(files: File[], append = false) {
        const validFiles = Array.from(files)
            .filter(file => file.size > 0 && validateFile(file));

        if (validFiles.length === 0) return;

        const newFileObjects = validFiles.map(createFileObject);

        if (append) {
            selectedFiles.value = [...selectedFiles.value, ...newFileObjects];
        } else {
            selectedFiles.value = newFileObjects;
        }
    }

    function handleFileSelect(event: Event) {
        const input = event.target as HTMLInputElement;
        if (!input.files?.length) return;
        
        handleFiles(Array.from(input.files));
    }

    function handleAdditionalFiles(event: Event) {
        const input = event.target as HTMLInputElement;
        if (!input.files?.length) return;
        
        handleFiles(Array.from(input.files), true);
    }

    function handleDrop(event: DragEvent) {
        isDragging.value = false;
        
        if (!event.dataTransfer?.files) return;
        
        handleFiles(Array.from(event.dataTransfer.files), true);
    }

    function removeFile(index: number) {
        const file = selectedFiles.value[index];
        if (file.preview) {
            URL.revokeObjectURL(file.preview);
        }
        selectedFiles.value.splice(index, 1);
    }

    function resetFiles() {
        selectedFiles.value.forEach(file => {
            if (file.preview) {
                URL.revokeObjectURL(file.preview);
            }
        });
        selectedFiles.value = [];
    }

    return {
        selectedFiles,
        isDragging,
        handleDrop,
        handleFileSelect,
        handleAdditionalFiles,
        removeFile,
        resetFiles
    };
} 