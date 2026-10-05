<template>
  <div class="fixed inset-0 bg-black text-white overflow-hidden flex flex-col z-50">
    <Head title="Scan a document" />
    <!-- Header -->
    <div class="absolute top-0 left-0 right-0 z-30 p-4 flex justify-between items-center bg-gradient-to-b from-black/70 to-transparent pointer-events-none">
      <Link :href="route('dashboard')" aria-label="Close scanner" class="text-white p-2 rounded-full bg-black/30 hover:bg-black/50 backdrop-blur-sm transition pointer-events-auto">
        <XMarkIcon class="w-6 h-6" aria-hidden="true" />
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
        <button v-if="errorAction === 'scanner'" @click="loadOpenCV" class="mt-4 px-6 py-2 bg-amber-600 rounded-lg hover:bg-amber-500 transition">
          Retry Scanner
        </button>
        <button v-else-if="step === 'camera'" @click="startCamera" class="mt-4 px-6 py-2 bg-amber-600 rounded-lg hover:bg-amber-500 transition">
          Retry Camera
        </button>
        <button v-else @click="error = null" class="mt-4 px-6 py-2 bg-amber-600 rounded-lg hover:bg-amber-500 transition">
          Back to Scan
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
            <div>
              <label class="block text-sm font-medium text-zinc-300 mb-2">Tags</label>
              <TagSelector
                v-model="tagIds"
                placeholder="Search or create tags..."
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
          @error="onImageError"
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
import { Head, Link, router } from '@inertiajs/vue3';
import { XMarkIcon, PencilSquareIcon, ExclamationTriangleIcon, SparklesIcon } from '@heroicons/vue/24/outline';
import Toast from '@/Components/Common/Toast.vue';
import PerspectiveCropper from './PerspectiveCropper.vue';
import CollectionSelector from '@/Components/Domain/CollectionSelector.vue';
import TagSelector from '@/Components/Domain/TagSelector.vue';
import { jsPDF } from 'jspdf';

// State
const step = ref('camera'); // 'camera', 'review'
const mode = ref('receipt'); // 'receipt', 'document'
const note = ref('');
const collectionIds = ref([]);
const tagIds = ref([]);
const showNoteInput = ref(false);
const error = ref(null);
const errorAction = ref(null);
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
let disposed = false;
let cameraRequest = 0;
let cancelCameraWait = null;
let cancelImageWait = null;
let cancelUpload = null;
let script = null;
let cvTimer = null;
let runtimeReady = null;

const cleanupOpenCVLoad = () => {
  clearTimeout(cvTimer);
  if (script) {
    script.onload = null;
    script.onerror = null;
    script.remove();
    script = null;
  }
  if (window.cv?.onRuntimeInitialized === runtimeReady) {
    window.cv.onRuntimeInitialized = null;
  }
  runtimeReady = null;
};

const loadOpenCV = () => {
  cleanupOpenCVLoad();
  if (errorAction.value === 'scanner') error.value = null;
  if (window.cv?.getBuildInformation) {
    cvLoaded.value = true;
    return;
  }
  cvLoaded.value = false;
  const fail = () => {
    cleanupOpenCVLoad();
    errorAction.value = 'scanner';
    error.value = 'Scanner could not load. Please retry.';
  };
  runtimeReady = () => {
    cvLoaded.value = true;
    cleanupOpenCVLoad();
  };
  script = document.createElement('script');
  script.src = '/vendor/opencv.js?v=3';
  script.async = true;
  script.onerror = fail;
  script.onload = () => {
    if (window.cv?.getBuildInformation) {
      runtimeReady();
    } else if (window.cv) {
      window.cv.onRuntimeInitialized = runtimeReady;
    } else {
      fail();
    }
  };
  cvTimer = setTimeout(fail, 15000);
  document.body.appendChild(script);
};

const getCropperImageElement = () => {
  if (!cropperRef.value || typeof cropperRef.value.getImageElement !== 'function') {
    return null;
  }

  return cropperRef.value.getImageElement();
};

const orderPoints = (points) => {
  if (!points || points.length !== 4 || points.some(point => !point || !Number.isFinite(point.x) || !Number.isFinite(point.y))) {
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
  if (!imgElement) throw new Error('Image is not ready. Please retake the scan.');
  if (!imgElement.complete) {
    await new Promise((resolve, reject) => {
      const finish = (failure) => {
        clearTimeout(timer);
        imgElement.removeEventListener('load', loaded);
        imgElement.removeEventListener('error', failed);
        cancelImageWait = null;
        failure ? reject(failure) : resolve();
      };
      const loaded = () => finish();
      const failed = () => finish(new Error('Image could not load. Please retake the scan.'));
      const timer = setTimeout(failed, 15000);
      cancelImageWait = failed;
      imgElement.addEventListener('load', loaded, { once: true });
      imgElement.addEventListener('error', failed, { once: true });
    });
  }
  if (!imgElement.naturalWidth || !imgElement.naturalHeight) {
    throw new Error('Image could not load. Please retake the scan.');
  }
};

const onImageError = () => {
  errorAction.value = 'review';
  error.value = 'Image could not load. Please retake the scan.';
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
    
    const resources = [];
    try {
        const { mat: src } = readImageMat(imgElement);
        resources.push(src);
        const gray = new cv.Mat();
        resources.push(gray);
        const blurred = new cv.Mat();
        resources.push(blurred);
        const edges = new cv.Mat();
        resources.push(edges);
        
        // 1. Preprocessing
        cv.cvtColor(src, gray, cv.COLOR_RGBA2GRAY, 0);
        cv.GaussianBlur(gray, blurred, new cv.Size(5, 5), 0, 0, cv.BORDER_DEFAULT);
        cv.Canny(blurred, edges, 75, 200);
        const kernel = cv.getStructuringElement(cv.MORPH_RECT, new cv.Size(5, 5));
        resources.push(kernel);
        cv.morphologyEx(edges, edges, cv.MORPH_CLOSE, kernel);

        // 2. Find Contours
        const contours = new cv.MatVector();
        resources.push(contours);
        const hierarchy = new cv.Mat();
        resources.push(hierarchy);
        cv.findContours(edges, contours, hierarchy, cv.RETR_LIST, cv.CHAIN_APPROX_SIMPLE);

        const imageArea = src.rows * src.cols;
        const minAreaRatio = mode.value === 'receipt' ? 0.08 : 0.2;
        const minArea = imageArea * minAreaRatio;
        let finalPoints = null;
        const contourList = [];

        for (let i = 0; i < contours.size(); ++i) {
            const contour = contours.get(i);
            resources.push(contour);
            contourList.push({ contour, area: cv.contourArea(contour) });
        }

        contourList.sort((a, b) => b.area - a.area);

        for (const { contour, area } of contourList.slice(0, 5)) {
            if (area < minArea) {
                continue;
            }
            const peri = cv.arcLength(contour, true);
            const approx = new cv.Mat();
            resources.push(approx);
            cv.approxPolyDP(contour, approx, 0.02 * peri, true);

            if (approx.rows === 4) {
                finalPoints = [];
                for (let j = 0; j < 4; j++) {
                    finalPoints.push({
                        x: approx.data32S[j * 2],
                        y: approx.data32S[j * 2 + 1]
                    });
                }
                break;
            }
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

        if (finalPoints) {
            return orderPoints(finalPoints);
        }

        return null;
    } catch (e) {
        console.error("OpenCV processing error:", e);
        return null;
    } finally {
        resources.reverse().forEach(resource => resource.delete());
    }
};

const runAutoDetect = async () => {
    if (detecting.value || processing.value || disposed) return;
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
    } catch (err) {
        if (!disposed && step.value === 'review') {
            errorAction.value = 'review';
            error.value = err.message;
        }
    } finally {
        detecting.value = false;
    }
};

// Update points from child
const onPointsUpdate = (points) => {
    currentPoints.value = points;
};

const stopCamera = () => {
  cameraRequest++;
  cancelCameraWait?.();
  if (stream) {
    stream.getTracks().forEach(track => track.stop());
    stream = null;
  }
  if (video.value) video.value.srcObject = null;
  cameraReady.value = false;
};

const startCamera = async () => {
  stopCamera();
  const request = cameraRequest;
  error.value = null;
  errorAction.value = 'camera';
  try {
    await new Promise((resolve, reject) => {
      let element = null;
      let settled = false;
      const finish = (failure) => {
        if (settled) return;
        settled = true;
        clearTimeout(timer);
        element?.removeEventListener('loadedmetadata', loaded);
        element?.removeEventListener('error', failed);
        cancelCameraWait = null;
        failure ? reject(failure) : resolve();
      };
      const loaded = () => {
        cameraReady.value = true;
        finish();
      };
      const failed = () => finish(new Error('Could not access camera. Please check permissions and retry.'));
      const timer = setTimeout(failed, 15000);
      cancelCameraWait = failed;
      navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment', width: { ideal: 1920 }, height: { ideal: 1080 } }
      }).then(candidate => {
        if (settled || disposed || request !== cameraRequest) {
          candidate.getTracks().forEach(track => track.stop());
          return;
        }
        stream = candidate;
        element = video.value;
        if (!element) {
          failed();
          return;
        }
        element.addEventListener('loadedmetadata', loaded, { once: true });
        element.addEventListener('error', failed, { once: true });
        element.srcObject = stream;
        if (element.readyState >= 1) loaded();
      }).catch(failed);
    });
  } catch (err) {
    if (!disposed && request === cameraRequest) {
      stopCamera();
      error.value = err.message;
    }
  }
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

  try {
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
  } catch (err) {
    errorAction.value = 'camera';
    error.value = 'Could not capture image. Please retry the camera.';
  }
};

const retake = () => {
  cancelImageWait?.();
  capturedImage.value = null;
  detectedPoints.value = null;
  currentPoints.value = [];
  step.value = 'camera';
  startCamera();
};

// --- Processing & Warping ---

const processAndUpload = async () => {
  if (processing.value || detecting.value || disposed) return;
  processing.value = true;
  error.value = null;
  errorAction.value = 'review';
  const resources = [];
  let uploadTimer = null;

  try {
    if (!cvLoaded.value) {
      errorAction.value = 'scanner';
      throw new Error('Scanner is still loading. Please retry the scanner.');
    }
    const srcImg = getCropperImageElement();
    await ensureImageReady(srcImg);
    const sortedPts = orderPoints(currentPoints.value);
    const validPoints = sortedPts && sortedPts.every((point, index) => {
      if (!Number.isFinite(point.x) || !Number.isFinite(point.y) ||
          point.x < 0 || point.y < 0 || point.x > srcImg.naturalWidth || point.y > srcImg.naturalHeight) return false;
      const next = sortedPts[(index + 1) % 4];
      const after = sortedPts[(index + 2) % 4];
      return (next.x - point.x) * (after.y - next.y) - (next.y - point.y) * (after.x - next.x) > 0;
    });
    if (!validPoints) throw new Error('Invalid crop. Adjust the corners and retry.');

    // Determine output width/height
    const widthTop = Math.hypot(sortedPts[1].x - sortedPts[0].x, sortedPts[1].y - sortedPts[0].y);
    const widthBottom = Math.hypot(sortedPts[2].x - sortedPts[3].x, sortedPts[2].y - sortedPts[3].y);
    const maxWidth = Math.round(Math.max(widthTop, widthBottom));

    const heightLeft = Math.hypot(sortedPts[0].x - sortedPts[3].x, sortedPts[0].y - sortedPts[3].y);
    const heightRight = Math.hypot(sortedPts[1].x - sortedPts[2].x, sortedPts[1].y - sortedPts[2].y);
    const maxHeight = Math.round(Math.max(heightLeft, heightRight));
    if (maxWidth < 1 || maxHeight < 1) throw new Error('Invalid crop. Adjust the corners and retry.');
    const { mat: srcMat } = readImageMat(srcImg);
    resources.push(srcMat);

    // Source points matrix
    const srcTri = cv.matFromArray(4, 1, cv.CV_32FC2, [
        sortedPts[0].x, sortedPts[0].y,
        sortedPts[1].x, sortedPts[1].y,
        sortedPts[2].x, sortedPts[2].y,
        sortedPts[3].x, sortedPts[3].y
    ]);

    resources.push(srcTri);

    // Destination points matrix (rect)
    const dstTri = cv.matFromArray(4, 1, cv.CV_32FC2, [
        0, 0,
        maxWidth, 0,
        maxWidth, maxHeight,
        0, maxHeight
    ]);

    resources.push(dstTri);

    // Compute Homography
    const M = cv.getPerspectiveTransform(srcTri, dstTri);
    resources.push(M);
    const dstMat = new cv.Mat();
    resources.push(dstMat);
    const dsize = new cv.Size(maxWidth, maxHeight);
    
    // Warp
    cv.warpPerspective(srcMat, dstMat, M, dsize, cv.INTER_LINEAR, cv.BORDER_CONSTANT, new cv.Scalar());

    // Convert back to canvas/blob
    const canvas = document.createElement('canvas');
    cv.imshow(canvas, dstMat);
    
    resources.reverse().forEach(resource => resource.delete());
    resources.length = 0;

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
    if (tagIds.value.length > 0) {
      tagIds.value.forEach((id, index) => {
        formData.append(`tag_ids[${index}]`, id);
      });
    }

    await new Promise(resolve => {
      uploadTimer = setTimeout(() => {
        error.value = 'Upload timed out. Please retry.';
        cancelUpload?.cancel();
      }, 60000);
      router.post(route('documents.store'), formData, {
        forceFormData: true,
        onCancelToken: token => { cancelUpload = token; },
        onSuccess: (page) => {
          const outcome = page.props.flash.upload_results[0];
          if (outcome.status === 'failed') {
            error.value = outcome.message;
            return;
          }
          router.visit(route(mode.value === 'document' ? 'documents.index' : 'receipts.index'));
        },
        onError: errors => { error.value = 'Upload failed: ' + Object.values(errors).join(', '); },
        onNetworkError: () => { error.value = 'Upload failed. Check your connection and retry.'; return false; },
        onHttpException: () => { error.value = 'Upload failed. Please retry.'; return false; },
        onCancel: () => { error.value ||= 'Upload cancelled. Please retry.'; },
        onFinish: () => {
          clearTimeout(uploadTimer);
          cancelUpload = null;
          resolve();
        }
      });
    });
  } catch (err) {
    error.value = err.message || 'Failed to process image. Please retry.';
  } finally {
    clearTimeout(uploadTimer);
    resources.reverse().forEach(resource => resource.delete());
    processing.value = false;
  }
};

// Service Worker Registration for PWA
const registerServiceWorker = async () => {
  if ('serviceWorker' in navigator) {
    try {
      const registration = await navigator.serviceWorker.register('/sw-scanner.js');
      console.log('Scanner Service Worker registered:', registration.scope);
    } catch (err) {
      console.error('Scanner Service Worker registration failed:', err);
    }
  }
};

// Lifecycle
onMounted(() => {
  loadOpenCV();
  startCamera();
  registerServiceWorker();
});

onUnmounted(() => {
  disposed = true;
  stopCamera();
  cancelImageWait?.();
  cleanupOpenCVLoad();
  cancelUpload?.cancel();
});
</script>
