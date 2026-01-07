<template>
  <div class="fixed inset-0 bg-black text-white overflow-hidden flex flex-col z-50">
    <!-- Header -->
    <div class="absolute top-0 left-0 right-0 z-30 p-4 flex justify-between items-center bg-gradient-to-b from-black/70 to-transparent pointer-events-none">
      <Link :href="route('dashboard')" class="text-white p-2 rounded-full bg-black/30 hover:bg-black/50 backdrop-blur-sm transition pointer-events-auto">
        <XMarkIcon class="w-6 h-6" />
      </Link>
      
      <!-- Mode Switcher (Camera Step Only) -->
      <div v-if="step === 'camera'" class="bg-black/40 backdrop-blur-md rounded-full p-1 flex border border-white/20 pointer-events-auto">
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
      <div class="w-10"></div>
    </div>

    <!-- Error/Permission Message -->
    <div v-if="error" class="absolute inset-0 z-50 flex items-center justify-center bg-black/90 p-6 text-center">
      <div class="max-w-md">
        <ExclamationTriangleIcon class="w-12 h-12 text-amber-500 mx-auto mb-4" />
        <p class="text-lg font-medium mb-2">{{ error }}</p>
        <button @click="startCamera" class="mt-4 px-6 py-2 bg-amber-600 rounded-lg hover:bg-amber-500 transition">
          Retry Camera
        </button>
      </div>
    </div>

    <!-- Step 1: Camera -->
    <div v-show="step === 'camera'" class="relative flex-1 bg-black flex flex-col h-full">
      <video ref="video" autoplay playsinline class="absolute inset-0 w-full h-full object-cover"></video>
      
      <!-- Note Input Overlay -->
      <div v-if="showNoteInput" class="absolute inset-0 z-40 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
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

      <!-- Camera Controls Footer -->
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

    <!-- Step 2: Review & Perspective Crop -->
    <div v-if="step === 'review'" class="flex flex-col h-full bg-zinc-900">
      <!-- Cropper Container (Flex Grow) -->
      <div class="flex-1 relative overflow-hidden bg-black/50">
        <PerspectiveCropper 
          ref="cropperRef"
          :src="capturedImage"
          :initial-points="detectedPoints"
          @update:points="onPointsUpdate"
        />
        
        <!-- Loading Overlay -->
        <div v-if="processing || detecting" class="absolute inset-0 z-50 bg-black/50 flex items-center justify-center">
            <div class="bg-zinc-900 px-6 py-4 rounded-xl flex items-center gap-3 border border-zinc-700 shadow-xl">
                <span class="animate-spin rounded-full h-5 w-5 border-2 border-amber-500 border-t-transparent"></span>
                <span class="text-white font-medium">{{ detecting ? 'Detecting...' : 'Processing...' }}</span>
            </div>
        </div>
      </div>

      <!-- Action Footer (Fixed Height) -->
      <div class="h-24 bg-zinc-900 border-t border-zinc-800 flex items-center justify-between px-6 z-30 shrink-0">
        <div class="flex gap-4">
            <button @click="retake" class="text-white/70 hover:text-white font-medium px-2 py-2">
            Retake
            </button>
            <button 
                @click="runAutoDetect" 
                v-if="cvLoaded"
                class="p-2 text-amber-500 hover:text-amber-400 hover:bg-white/5 rounded-full transition"
                title="Auto-detect borders"
            >
                <SparklesIcon class="w-6 h-6" />
            </button>
        </div>
        
        <button 
          @click="processAndUpload" 
          :disabled="processing || detecting"
          class="bg-amber-500 text-white px-8 py-3 rounded-full font-bold shadow-lg hover:bg-amber-400 active:scale-95 transition flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
        >
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
import { XMarkIcon, PencilSquareIcon, ExclamationTriangleIcon, SparklesIcon } from '@heroicons/vue/24/outline';
import Toast from '@/Components/Common/Toast.vue';
import PerspectiveCropper from './PerspectiveCropper.vue';
import { jsPDF } from 'jspdf';

// State
const step = ref('camera'); // 'camera', 'review'
const mode = ref('receipt'); // 'receipt', 'document'
const note = ref('');
const showNoteInput = ref(false);
const error = ref(null);
const processing = ref(false);
const detecting = ref(false);
const cvLoaded = ref(false);

// Refs
const video = ref(null);
const capturedImage = ref(null);
const cropperRef = ref(null);
const detectedPoints = ref(null); // Array of 4 points {x,y}
const currentPoints = ref([]); // Points from cropper

let stream = null;

// Load OpenCV
const loadOpenCV = () => {
  if (window.cv && window.cv.getBuildInformation) {
    cvLoaded.value = true;
    return;
  }
  const script = document.createElement('script');
  script.src = '/vendor/opencv.js';
  script.async = true;
  script.onload = () => {
    if (cv.getBuildInformation) {
        cvLoaded.value = true;
    } else {
        cv.onRuntimeInitialized = () => {
            cvLoaded.value = true;
        };
    }
  };
  document.body.appendChild(script);
};

// Document Detection (Return 4 points sorted TL, TR, BR, BL)
const detectDocument = async (imgElement) => {
    if (!cvLoaded.value) return null;
    
    try {
        const src = cv.imread(imgElement);
        const dst = new cv.Mat();
        
        // 1. Preprocessing
        cv.cvtColor(src, dst, cv.COLOR_RGBA2GRAY, 0);
        cv.GaussianBlur(dst, dst, new cv.Size(5, 5), 0, 0, cv.BORDER_DEFAULT);
        cv.Canny(dst, dst, 75, 200);

        // 2. Find Contours
        const contours = new cv.MatVector();
        const hierarchy = new cv.Mat();
        cv.findContours(dst, contours, hierarchy, cv.RETR_EXTERNAL, cv.CHAIN_APPROX_SIMPLE);

        let maxArea = 0;
        let finalPoints = null;

        // 3. Find largest contour
        for (let i = 0; i < contours.size(); ++i) {
            let contour = contours.get(i);
            let area = cv.contourArea(contour);
            
            if (area > 5000) { 
                let peri = cv.arcLength(contour, true);
                let approx = new cv.Mat();
                cv.approxPolyDP(contour, approx, 0.02 * peri, true);

                if (area > maxArea) {
                    maxArea = area;
                    
                    if (approx.rows === 4) {
                        // Found a quad!
                        finalPoints = [];
                        for(let j = 0; j < 4; j++) {
                            finalPoints.push({
                                x: approx.data32S[j * 2],
                                y: approx.data32S[j * 2 + 1]
                            });
                        }
                    } else {
                        // Fallback: Use bounding rect if not a perfect quad
                        const rect = cv.boundingRect(approx);
                        finalPoints = [
                            { x: rect.x, y: rect.y },
                            { x: rect.x + rect.width, y: rect.y },
                            { x: rect.x + rect.width, y: rect.y + rect.height },
                            { x: rect.x, y: rect.y + rect.height }
                        ];
                    }
                }
                approx.delete();
            }
        }

        // Cleanup
        src.delete();
        dst.delete();
        contours.delete();
        hierarchy.delete();

        if (finalPoints) {
            // Sort points: TL, TR, BR, BL
            // 1. Sort by Y to separate Top/Bottom
            finalPoints.sort((a, b) => a.y - b.y);
            const top = finalPoints.slice(0, 2).sort((a, b) => a.x - b.x); // TL, TR
            const bottom = finalPoints.slice(2, 4).sort((a, b) => b.x - a.x); // BR, BL (ensure BR is right-most of bottom set? No, verify standard)
            // Actually, for BR/BL logic:
            // Standard order often: TL, TR, BR, BL. 
            // My previous sort was: BR is bottom[0] (max X of bottom pair), BL is bottom[1] (min X of bottom pair).
            // Let's stick to consistent TL, TR, BR, BL order for the component.
            // Component draws polygon in array order. To avoid hourglass, order should be clock-wise: TL -> TR -> BR -> BL.
            
            // Re-sort bottom for clock-wise: TR -> BR -> BL -> TL
            // Top: [TL, TR] (sorted by X)
            // Bottom: need BR (max X), BL (min X)
            const bl = bottom[1]; 
            const br = bottom[0]; // If sorted by b.x - a.x (descending), index 0 is largest X (Right)

            // BUT my previous sort was `b.x - a.x` (descending). So `bottom` is [Right-most, Left-most].
            // So bottom[0] is BR, bottom[1] is BL.
            // Clockwise: TL, TR, BR, BL.
            return [top[0], top[1], bottom[0], bottom[1]]; 
        }

        return null;
    } catch (e) {
        console.error("OpenCV processing error:", e);
        return null;
    }
};

const runAutoDetect = async () => {
    detecting.value = true;
    if (cropperRef.value && cropperRef.value.imageElement) {
        const points = await detectDocument(cropperRef.value.imageElement);
        if (points) {
            detectedPoints.value = points;
            step.value = 'review'; 
        }
    }
    detecting.value = false;
};

// Update points from child
const onPointsUpdate = (points) => {
    currentPoints.value = points;
};

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
  stopCamera(); 
  step.value = 'review';
  
  // Attempt auto-detect immediately
  nextTick(async () => {
    detecting.value = true;
    if (cropperRef.value && cropperRef.value.imageElement) {
        // Wait for image load
        const img = cropperRef.value.imageElement;
        if (img.complete) {
            detectedPoints.value = await detectDocument(img);
        } else {
            img.onload = async () => {
                detectedPoints.value = await detectDocument(img);
            };
        }
    }
    detecting.value = false;
  });
};

const retake = () => {
  capturedImage.value = null;
  detectedPoints.value = null;
  step.value = 'camera';
  startCamera();
};

// --- Processing & Warping ---

const processAndUpload = async () => {
  if (!currentPoints.value || currentPoints.value.length !== 4) return;
  processing.value = true;

  try {
    const srcImg = cropperRef.value.imageElement;
    const srcMat = cv.imread(srcImg);
    
    // Sort points: TL, TR, BR, BL
    // currentPoints are {x, y} relative to natural image size
    const pts = currentPoints.value;
    
    // Simple sort based on x/y to ensure order (TL, TR, BR, BL)
    // 1. Sort by Y
    pts.sort((a, b) => a.y - b.y);
    // 2. Top two are TL/TR, Bottom two are BL/BR
    const top = pts.slice(0, 2).sort((a, b) => a.x - b.x);
    const bottom = pts.slice(2, 4).sort((a, b) => b.x - a.x); // BR is last
    // Correct order: TL, TR, BR, BL (OpenCV perspective standard is often 4 points in order)
    const sortedPts = [top[0], top[1], bottom[0], bottom[1]];

    // Determine output width/height
    const widthTop = Math.hypot(sortedPts[1].x - sortedPts[0].x, sortedPts[1].y - sortedPts[0].y);
    const widthBottom = Math.hypot(sortedPts[2].x - sortedPts[3].x, sortedPts[2].y - sortedPts[3].y);
    const maxWidth = Math.max(widthTop, widthBottom);

    const heightLeft = Math.hypot(sortedPts[0].x - sortedPts[3].x, sortedPts[0].y - sortedPts[3].y);
    const heightRight = Math.hypot(sortedPts[1].x - sortedPts[2].x, sortedPts[1].y - sortedPts[2].y);
    const maxHeight = Math.max(heightLeft, heightRight);

    // Source points matrix
    const srcTri = cv.matFromArray(4, 1, cv.CV_32FC2, [
        sortedPts[0].x, sortedPts[0].y,
        sortedPts[1].x, sortedPts[1].y,
        sortedPts[2].x, sortedPts[2].y,
        sortedPts[3].x, sortedPts[3].y
    ]);

    // Destination points matrix (rect)
    const dstTri = cv.matFromArray(4, 1, cv.CV_32FC2, [
        0, 0,
        maxWidth, 0,
        maxWidth, maxHeight,
        0, maxHeight
    ]);

    // Compute Homography
    const M = cv.getPerspectiveTransform(srcTri, dstTri);
    const dstMat = new cv.Mat();
    const dsize = new cv.Size(maxWidth, maxHeight);
    
    // Warp
    cv.warpPerspective(srcMat, dstMat, M, dsize, cv.INTER_LINEAR, cv.BORDER_CONSTANT, new cv.Scalar());

    // Convert back to canvas/blob
    const canvas = document.createElement('canvas');
    cv.imshow(canvas, dstMat);
    
    // Clean up mats
    srcMat.delete();
    dstMat.delete();
    srcTri.delete();
    dstTri.delete();
    M.delete();

    // 2. Generate PDF
    const imgData = canvas.toDataURL('image/jpeg', 0.85);
    const pdf = new jsPDF({
      orientation: maxWidth > maxHeight ? 'l' : 'p',
      unit: 'px',
      format: [maxWidth, maxHeight]
    });
    
    pdf.addImage(imgData, 'JPEG', 0, 0, maxWidth, maxHeight);
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
         // Done
      },
      onError: (errors) => {
        processing.value = false;
        error.value = "Upload failed: " + Object.values(errors).join(', ');
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
  loadOpenCV();
  startCamera();
});

onUnmounted(() => {
  stopCamera();
});
</script>
