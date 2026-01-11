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
        <div class="bg-zinc-900 border border-zinc-700 p-6 rounded-2xl w-full max-w-md shadow-2xl">
          <h3 class="text-lg font-semibold mb-4 text-white">Add Details</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-zinc-300 mb-2">Note</label>
              <textarea
                v-model="note"
                rows="3"
                class="w-full bg-zinc-800 border-zinc-700 rounded-xl text-white placeholder-zinc-500 focus:ring-amber-500 focus:border-amber-500"
                placeholder="Details about this scan..."
              ></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-zinc-300 mb-2">Collections</label>
              <CollectionSelector
                v-model="collectionIds"
                placeholder="Search or create collections..."
                :allow-create="true"
              />
            </div>
          </div>
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
import CollectionSelector from '@/Components/Domain/CollectionSelector.vue';
import { jsPDF } from 'jspdf';

// State
const step = ref('camera'); // 'camera', 'review'
const mode = ref('receipt'); // 'receipt', 'document'
const note = ref('');
const collectionIds = ref([]);
const showNoteInput = ref(false);
const error = ref(null);
const processing = ref(false);
const detecting = ref(false);
const cvLoaded = ref(false);
const cameraReady = ref(false);

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
    if (window.cv && window.cv.getBuildInformation) {
        cvLoaded.value = true;
    } else {
        window.cv.onRuntimeInitialized = () => {
            cvLoaded.value = true;
        };
    }
  };
  document.body.appendChild(script);
};

const getCropperImageElement = () => {
  if (!cropperRef.value || typeof cropperRef.value.getImageElement !== 'function') {
    return null;
  }

  return cropperRef.value.getImageElement();
};

const orderPoints = (points) => {
  if (!points || points.length !== 4) {
    return null;
  }

  const pts = points.map((point) => ({ x: point.x, y: point.y }));
  const sums = pts.map((point) => point.x + point.y);
  const diffs = pts.map((point) => point.y - point.x);

  const topLeft = pts[sums.indexOf(Math.min(...sums))];
  const bottomRight = pts[sums.indexOf(Math.max(...sums))];
  const topRight = pts[diffs.indexOf(Math.min(...diffs))];
  const bottomLeft = pts[diffs.indexOf(Math.max(...diffs))];

  return [topLeft, topRight, bottomRight, bottomLeft];
};

const ensureImageReady = async (imgElement) => {
  if (!imgElement || imgElement.complete) {
    return;
  }

  if (typeof imgElement.decode === 'function') {
    await imgElement.decode();
    return;
  }

  await new Promise((resolve) => {
    imgElement.addEventListener('load', resolve, { once: true });
  });
};

const readImageMat = (imgElement) => {
  const width = imgElement.naturalWidth || imgElement.width;
  const height = imgElement.naturalHeight || imgElement.height;
  const canvas = document.createElement('canvas');
  canvas.width = width;
  canvas.height = height;
  const ctx = canvas.getContext('2d');
  ctx.drawImage(imgElement, 0, 0, width, height);

  return { mat: cv.imread(canvas), width, height };
};

// Document Detection (Return 4 points sorted TL, TR, BR, BL)
const detectDocument = async (imgElement) => {
    if (!cvLoaded.value || !imgElement) return null;
    
    try {
        const { mat: src } = readImageMat(imgElement);
        const gray = new cv.Mat();
        const blurred = new cv.Mat();
        const edges = new cv.Mat();
        
        // 1. Preprocessing
        cv.cvtColor(src, gray, cv.COLOR_RGBA2GRAY, 0);
        cv.GaussianBlur(gray, blurred, new cv.Size(5, 5), 0, 0, cv.BORDER_DEFAULT);
        cv.Canny(blurred, edges, 75, 200);
        const kernel = cv.getStructuringElement(cv.MORPH_RECT, new cv.Size(5, 5));
        cv.morphologyEx(edges, edges, cv.MORPH_CLOSE, kernel);
        kernel.delete();

        // 2. Find Contours
        const contours = new cv.MatVector();
        const hierarchy = new cv.Mat();
        cv.findContours(edges, contours, hierarchy, cv.RETR_LIST, cv.CHAIN_APPROX_SIMPLE);

        const imageArea = src.rows * src.cols;
        const minAreaRatio = mode.value === 'receipt' ? 0.08 : 0.2;
        const minArea = imageArea * minAreaRatio;
        let finalPoints = null;
        const contourList = [];

        for (let i = 0; i < contours.size(); ++i) {
            const contour = contours.get(i);
            contourList.push({ contour, area: cv.contourArea(contour) });
        }

        contourList.sort((a, b) => b.area - a.area);

        for (const { contour, area } of contourList.slice(0, 5)) {
            if (area < minArea) {
                continue;
            }
            const peri = cv.arcLength(contour, true);
            const approx = new cv.Mat();
            cv.approxPolyDP(contour, approx, 0.02 * peri, true);

            if (approx.rows === 4) {
                finalPoints = [];
                for (let j = 0; j < 4; j++) {
                    finalPoints.push({
                        x: approx.data32S[j * 2],
                        y: approx.data32S[j * 2 + 1]
                    });
                }
                approx.delete();
                break;
            }

            approx.delete();
        }

        if (!finalPoints && contourList.length > 0) {
            const largest = contourList[0];
            if (largest.area >= minArea) {
                const rect = cv.boundingRect(largest.contour);
                finalPoints = [
                    { x: rect.x, y: rect.y },
                    { x: rect.x + rect.width, y: rect.y },
                    { x: rect.x + rect.width, y: rect.y + rect.height },
                    { x: rect.x, y: rect.y + rect.height }
                ];
            } else {
                finalPoints = [
                    { x: 0, y: 0 },
                    { x: src.cols, y: 0 },
                    { x: src.cols, y: src.rows },
                    { x: 0, y: src.rows }
                ];
            }
        }

        // Cleanup
        contourList.forEach(({ contour }) => contour.delete());
        src.delete();
        gray.delete();
        blurred.delete();
        edges.delete();
        contours.delete();
        hierarchy.delete();

        if (finalPoints) {
            return orderPoints(finalPoints);
        }

        return null;
    } catch (e) {
        console.error("OpenCV processing error:", e);
        return null;
    }
};

const runAutoDetect = async () => {
    detecting.value = true;
    try {
        const img = getCropperImageElement();
        if (!img) {
            return;
        }

        await ensureImageReady(img);
        const points = await detectDocument(img);
        if (points) {
            detectedPoints.value = points;
        }
    } finally {
        detecting.value = false;
    }
};

// Update points from child
const onPointsUpdate = (points) => {
    currentPoints.value = points;
};

// Camera Logic
const startCamera = async () => {
  error.value = null;
  cameraReady.value = false;
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
      video.value.addEventListener('loadedmetadata', () => {
        cameraReady.value = true;
      }, { once: true });
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
  cameraReady.value = false;
};

const setMode = (newMode) => {
  mode.value = newMode;
};

// Capture Logic
const capture = async () => {
  if (!video.value) return;
  if (!cameraReady.value || video.value.videoWidth === 0 || video.value.videoHeight === 0) {
    return;
  }

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
    await runAutoDetect();
  });
};

const retake = () => {
  capturedImage.value = null;
  detectedPoints.value = null;
  currentPoints.value = [];
  step.value = 'camera';
  startCamera();
};

// --- Processing & Warping ---

const processAndUpload = async () => {
  if (!currentPoints.value || currentPoints.value.length !== 4) return;
  processing.value = true;

  try {
    if (!cvLoaded.value) {
      error.value = 'Scanner is still loading. Please try again.';
      processing.value = false;
      return;
    }

    const srcImg = getCropperImageElement();
    if (!srcImg) {
      error.value = 'Image not ready yet. Please try again.';
      processing.value = false;
      return;
    }

    await ensureImageReady(srcImg);
    const { mat: srcMat } = readImageMat(srcImg);
    
    const sortedPts = orderPoints([...currentPoints.value]);
    if (!sortedPts) {
      error.value = 'Could not determine crop points.';
      processing.value = false;
      return;
    }

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
    if (collectionIds.value.length > 0) {
      collectionIds.value.forEach((id, index) => {
        formData.append(`collection_ids[${index}]`, id);
      });
    }

    // 4. Upload
    router.post(route('documents.store'), formData, {
      forceFormData: true,
      onSuccess: () => {
         note.value = '';
         collectionIds.value = [];
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
