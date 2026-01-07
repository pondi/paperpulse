<template>
  <div class="relative w-full h-full bg-black overflow-hidden select-none touch-none" ref="container">
    <!-- The Image (fitted to container) -->
    <img 
      ref="imageRef" 
      :src="src" 
      class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 max-w-full max-h-full pointer-events-none"
      @load="onImageLoad"
    />

    <!-- SVG Overlay for Lines & Fill -->
    <svg v-if="dims.width" class="absolute inset-0 w-full h-full pointer-events-none z-10">
      <!-- Semi-transparent dark overlay outside the crop area -->
      <path 
        :d="overlayPath" 
        fill="rgba(0, 0, 0, 0.6)" 
        fill-rule="evenodd"
      />
      <!-- The polygon border -->
      <polygon 
        :points="polygonPoints" 
        fill="transparent" 
        stroke="#f59e0b" 
        stroke-width="2"
      />
    </svg>

    <!-- Draggable Handles -->
    <div v-if="dims.width" class="absolute inset-0 z-20">
      <div 
        v-for="(point, index) in points" 
        :key="index"
        class="absolute w-8 h-8 -ml-4 -mt-4 rounded-full border-2 border-amber-500 bg-amber-500/20 active:bg-amber-500/50 backdrop-blur-sm cursor-move touch-action-none flex items-center justify-center"
        :style="{ left: point.x + 'px', top: point.y + 'px' }"
        @mousedown.stop.prevent="startDrag(index, $event)"
        @touchstart.stop.prevent="startDrag(index, $event)"
      >
        <div class="w-2 h-2 bg-amber-500 rounded-full"></div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps({
  src: String,
  initialPoints: Array // [{x,y}, {x,y}, {x,y}, {x,y}] (Natural coordinates from OpenCV)
});

const emit = defineEmits(['update:points']);

const container = ref(null);
const imageRef = ref(null);
const points = ref([]); // [{x,y}, {x,y}, {x,y}, {x,y}] (TL, TR, BR, BL)
const dims = ref({ width: 0, height: 0, left: 0, top: 0, imgWidth: 0, imgHeight: 0 });
let draggingIndex = -1;

// --- Initialization ---

const initPoints = () => {
    if (!imageRef.value || !container.value) return;

    // Get displayed image dimensions
    const imgRect = imageRef.value.getBoundingClientRect();
    const contRect = container.value.getBoundingClientRect();

    dims.value = {
        width: contRect.width,
        height: contRect.height,
        left: contRect.left,
        top: contRect.top,
        imgWidth: imgRect.width,
        imgHeight: imgRect.height,
        imgLeft: imgRect.left - contRect.left, // Offset inside container
        imgTop: imgRect.top - contRect.top
    };

    // If we have auto-detected points from OpenCV (scaled to natural image size)
    if (props.initialPoints && props.initialPoints.length === 4) {
        const scaleX = imgRect.width / imageRef.value.naturalWidth;
        const scaleY = imgRect.height / imageRef.value.naturalHeight;
        
        points.value = props.initialPoints.map(p => ({
            x: p.x * scaleX + dims.value.imgLeft,
            y: p.y * scaleY + dims.value.imgTop
        }));
    } else {
        // Default to a slightly inset rectangle
        const padX = dims.value.imgWidth * 0.1;
        const padY = dims.value.imgHeight * 0.1;
        const x = dims.value.imgLeft + padX;
        const y = dims.value.imgTop + padY;
        const w = dims.value.imgWidth - (padX * 2);
        const h = dims.value.imgHeight - (padY * 2);

        points.value = [
            { x: x, y: y },
            { x: x + w, y: y },
            { x: x + w, y: y + h },
            { x: x, y: y + h }
        ];
    }
    
    emit('update:points', getRelativePoints());
};

const onImageLoad = () => {
    initPoints();
};

// Watch for prop changes to re-init
watch(() => props.initialPoints, () => {
    initPoints();
}, { deep: true });

// --- Computed ---

const polygonPoints = computed(() => {
    if (points.value.length !== 4) return '';
    return points.value.map(p => `${p.x},${p.y}`).join(' ');
});

const overlayPath = computed(() => {
    if (points.value.length !== 4 || !dims.value.width) return '';
    // M 0 0 h width v height h -width Z is the full container
    // The polygon part cuts a hole using fill-rule="evenodd"
    return `M 0 0 H ${dims.value.width} V ${dims.value.height} H 0 Z M ${points.value[0].x} ${points.value[0].y} L ${points.value[1].x} ${points.value[1].y} L ${points.value[2].x} ${points.value[2].y} L ${points.value[3].x} ${points.value[3].y} Z`;
});

// --- Interaction ---

const startDrag = (index, event) => {
    draggingIndex = index;
    // Add global listeners
    document.addEventListener('mousemove', onDrag);
    document.addEventListener('mouseup', stopDrag);
    document.addEventListener('touchmove', onDrag, { passive: false });
    document.addEventListener('touchend', stopDrag);
};

const onDrag = (event) => {
    if (draggingIndex === -1) return;
    event.preventDefault();

    let clientX, clientY;
    if (event.type.startsWith('touch')) {
        clientX = event.touches[0].clientX;
        clientY = event.touches[0].clientY;
    } else {
        clientX = event.clientX;
        clientY = event.clientY;
    }

    // Convert to relative container coords
    const rect = container.value.getBoundingClientRect();
    let x = clientX - rect.left;
    let y = clientY - rect.top;

    // Boundary checks (keep roughly within image area)
    // We allow a little flexibility but keeping it bounds is good UX
    // x = Math.max(dims.value.imgLeft, Math.min(x, dims.value.imgLeft + dims.value.imgWidth));
    // y = Math.max(dims.value.imgTop, Math.min(y, dims.value.imgTop + dims.value.imgHeight));
    
    // Hard clamp to container
    x = Math.max(0, Math.min(x, dims.value.width));
    y = Math.max(0, Math.min(y, dims.value.height));

    points.value[draggingIndex] = { x, y };
};

const stopDrag = () => {
    draggingIndex = -1;
    document.removeEventListener('mousemove', onDrag);
    document.removeEventListener('mouseup', stopDrag);
    document.removeEventListener('touchmove', onDrag);
    document.removeEventListener('touchend', stopDrag);
    
    emit('update:points', getRelativePoints());
};

// Helper: Return points relative to the *natural* image size for processing
const getRelativePoints = () => {
    if (!imageRef.value || points.value.length !== 4) return null;
    
    const scaleX = imageRef.value.naturalWidth / dims.value.imgWidth;
    const scaleY = imageRef.value.naturalHeight / dims.value.imgHeight;
    
    return points.value.map(p => ({
        x: (p.x - dims.value.imgLeft) * scaleX,
        y: (p.y - dims.value.imgTop) * scaleY
    }));
};

defineExpose({
    getImageElement: () => imageRef.value
});

</script>
