<template>
  <div class="fixed inset-0 bg-black text-white overflow-hidden flex flex-col z-50">
    <!-- Header -->
    <div class="absolute top-0 left-0 right-0 z-20 p-4 flex justify-between items-center bg-gradient-to-b from-black/70 to-transparent">
      <Link :href="route('dashboard')" class="text-white p-2 rounded-full bg-black/30 hover:bg-black/50 backdrop-blur-sm transition">
        <XMarkIcon class="w-6 h-6" />
      </Link>
      <div v-if="step === 'camera'" class="bg-black/40 backdrop-blur-md rounded-full p-1 flex border border-white/20">
        <button 
          @click="setMode('receipt')"
          :class="['px-4 py-1.5 rounded-full text-sm font-medium transition-all', mode === 'receipt' ? 'bg-amber-500 text-white shadow-sm' : 'text-white/70 hover:text-white']"
        >
          Receipt
        </button>
        <button 
          @click="setMode('document')"
          :class="['px-4 py-1.5 rounded-full text-sm font-medium transition-all', mode === 'document' ? 'bg-amber-500 text-white shadow-sm' : 'text-white/70 hover:text-white']"
        >
          Document
        </button>
      </div>
      <div class="w-10"></div> <!-- Spacer for alignment -->
    </div>

    <!-- Error/Permission Message -->
    <div v-if="error" class="absolute inset-0 z-30 flex items-center justify-center bg-black/80 p-6 text-center">
      <div class="max-w-md">
        <ExclamationTriangleIcon class="w-12 h-12 text-amber-500 mx-auto mb-4" />
        <p class="text-lg font-medium mb-2">{{ error }}</p>
        <button @click="startCamera" class="mt-4 px-6 py-2 bg-amber-600 rounded-lg hover:bg-amber-500 transition">
          Retry Camera
        </button>
      </div>
    </div>

    <!-- Step 1: Camera -->
    <div v-show="step === 'camera'" class="relative flex-1 bg-black flex flex-col">
      <video ref="video" autoplay playsinline class="absolute inset-0 w-full h-full object-cover"></video>
      
      <!-- Note Input Overlay -->
      <div v-if="showNoteInput" class="absolute inset-0 z-30 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-zinc-900 border border-zinc-700 p-6 rounded-2xl w-full max-w-sm shadow-2xl">
          <h3 class="text-lg font-semibold mb-4 text-white">Add Note</h3>
          <textarea 
            v-model="note" 
            rows="4" 
            class="w-full bg-zinc-800 border-zinc-700 rounded-xl text-white placeholder-zinc-500 focus:ring-amber-500 focus:border-amber-500"
            placeholder="Details about this scan..."
          ></textarea>
          <div class="mt-4 flex justify-end gap-3">
            <button @click="showNoteInput = false" class="px-4 py-2 text-zinc-300 hover:text-white">Done</button>
          </div>
        </div>
      </div>

      <!-- Controls -->
      <div class="absolute bottom-0 left-0 right-0 p-8 pb-12 bg-gradient-to-t from-black/90 via-black/50 to-transparent flex justify-between items-center z-20">
        <button @click="showNoteInput = true" class="p-3 rounded-full bg-white/10 backdrop-blur-md hover:bg-white/20 transition relative group">
          <PencilSquareIcon class="w-6 h-6 text-white" />
          <span v-if="note" class="absolute top-0 right-0 w-3 h-3 bg-amber-500 rounded-full border-2 border-black"></span>
        </button>
        
        <button @click="capture" class="w-20 h-20 rounded-full border-4 border-white flex items-center justify-center group active:scale-95 transition">
          <div class="w-16 h-16 bg-white rounded-full group-active:bg-amber-500 transition"></div>
        </button>

        <div class="w-12"></div> <!-- Spacer -->
      </div>
    </div>

    <!-- Step 2: Review & Crop -->
    <div v-show="step === 'review'" class="relative flex-1 bg-black flex flex-col">
      <div class="flex-1 relative bg-zinc-900 overflow-hidden">
        <img ref="imagePreview" :src="capturedImage" class="max-w-full max-h-full block" />
      </div>

      <div class="p-6 bg-zinc-900 border-t border-zinc-800 flex justify-between items-center z-20 pb-10">
        <button @click="retake" class="text-white/70 hover:text-white font-medium px-4 py-2">
          Retake
        </button>
        <button 
          @click="processAndUpload" 
          :disabled="processing"
          class="bg-amber-500 text-white px-8 py-3 rounded-full font-bold shadow-lg hover:bg-amber-400 active:scale-95 transition flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <span v-if="processing" class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></span>
          {{ processing ? 'Processing...' : 'Keep Scan' }}
        </button>
      </div>
    </div>
    
    <Toast />
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { XMarkIcon, PencilSquareIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import Toast from '@/Components/Common/Toast.vue';
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';
import { jsPDF } from 'jspdf';

// State
const step = ref('camera'); // 'camera', 'review'
const mode = ref('receipt'); // 'receipt', 'document'
const note = ref('');
const showNoteInput = ref(false);
const error = ref(null);
const processing = ref(false);

// Refs
const video = ref(null);
const imagePreview = ref(null);
const capturedImage = ref(null);
let stream = null;
let cropper = null;

// Camera Logic
const startCamera = async () => {
  error.value = null;
  try {
    const constraints = {
      video: {
        facingMode: 'environment', // Rear camera
        width: { ideal: 1920 },
        height: { ideal: 1080 }
      }
    };
    stream = await navigator.mediaDevices.getUserMedia(constraints);
    if (video.value) {
      video.value.srcObject = stream;
    }
  } catch (err) {
    console.error("Camera error:", err);
    error.value = "Could not access camera. Please check permissions.";
  }
};

const stopCamera = () => {
  if (stream) {
    stream.getTracks().forEach(track => track.stop());
    stream = null;
  }
};

const setMode = (newMode) => {
  mode.value = newMode;
};

// Capture Logic
const capture = () => {
  if (!video.value) return;

  const canvas = document.createElement('canvas');
  canvas.width = video.value.videoWidth;
  canvas.height = video.value.videoHeight;
  const ctx = canvas.getContext('2d');
  ctx.drawImage(video.value, 0, 0);
  
  capturedImage.value = canvas.toDataURL('image/jpeg', 0.9);
  stopCamera(); // Stop camera while reviewing
  step.value = 'review';
  
  // Initialize Cropper
  nextTick(() => {
    if (imagePreview.value) {
      cropper = new Cropper(imagePreview.value, {
        viewMode: 1,
        dragMode: 'move',
        autoCropArea: 0.8,
        restore: false,
        guides: true,
        center: true,
        highlight: false,
        cropBoxMovable: true,
        cropBoxResizable: true,
        toggleDragModeOnDblclick: false,
      });
    }
  });
};

const retake = () => {
  if (cropper) {
    cropper.destroy();
    cropper = null;
  }
  capturedImage.value = null;
  step.value = 'camera';
  startCamera();
};

// Processing & Upload Logic
const processAndUpload = async () => {
  if (!cropper) return;
  processing.value = true;

  try {
    // 1. Get cropped canvas
    const canvas = cropper.getCroppedCanvas({
      maxWidth: 2048,
      maxHeight: 2048,
      fillColor: '#fff',
    });

    // 2. Generate PDF
    const imgData = canvas.toDataURL('image/jpeg', 0.85);
    const pdf = new jsPDF({
      orientation: canvas.width > canvas.height ? 'l' : 'p',
      unit: 'px',
      format: [canvas.width, canvas.height]
    });
    
    pdf.addImage(imgData, 'JPEG', 0, 0, canvas.width, canvas.height);
    const pdfBlob = pdf.output('blob');
    
    // 3. Prepare Form Data
    const formData = new FormData();
    const filename = `scan_${new Date().toISOString().slice(0,19).replace(/[:T]/g,'-')}.pdf`;
    
    formData.append('files[]', pdfBlob, filename);
    formData.append('file_type', mode.value);
    if (note.value) {
      formData.append('note', note.value);
    }

    // 4. Upload
    router.post(route('documents.store'), formData, {
      forceFormData: true,
      onSuccess: () => {
        // Redirect is handled by the controller, but we can clean up here if needed
      },
      onError: (errors) => {
        processing.value = false;
        error.value = "Upload failed. " + Object.values(errors).join(', ');
        // Don't reset state fully so they can try again
      },
      onFinish: () => {
         // processing.value = false; // Usually handled by redirect
      }
    });

  } catch (err) {
    console.error("Processing error:", err);
    error.value = "Failed to process image.";
    processing.value = false;
  }
};

// Lifecycle
onMounted(() => {
  startCamera();
});

onUnmounted(() => {
  stopCamera();
  if (cropper) {
    cropper.destroy();
  }
});
</script>

<style>
/* Override Cropper styles for dark mode better visibility if needed */
.cropper-modal {
  background-color: rgba(0, 0, 0, 0.8);
}
.cropper-view-box {
  outline: 2px solid #f59e0b; /* Amber-500 */
}
.cropper-line {
  background-color: #f59e0b;
}
.cropper-point {
  background-color: #f59e0b;
}
</style>
