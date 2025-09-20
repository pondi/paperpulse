<template>
    <div class="relative">
        <!-- Input Field -->
        <div class="relative">
            <input
                :id="id"
                ref="input"
                type="text"
                :value="displayValue"
                :placeholder="placeholder"
                readonly
                @click="toggleCalendar"
                @keydown.escape="closeCalendar"
                @keydown.enter.prevent="toggleCalendar"
                class="block w-full rounded-md border-0 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm cursor-pointer"
                :class="[
                    error ? 'ring-red-500 dark:ring-red-500' : 'ring-gray-300 dark:ring-gray-600',
                    disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'
                ]"
                :disabled="disabled"
            />
            
            <!-- Calendar Icon -->
            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                <CalendarDaysIcon class="h-5 w-5 text-gray-400" />
            </div>

            <!-- Clear Button (when date is selected) -->
            <button
                v-if="modelValue && !disabled"
                @click.stop="clearDate"
                type="button"
                class="absolute inset-y-0 right-8 flex items-center pr-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            >
                <XMarkIcon class="h-4 w-4" />
            </button>
        </div>

        <!-- Calendar Dropdown -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0 translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 translate-y-1"
            >
                <div
                    v-if="showCalendar"
                    ref="calendar"
                    class="fixed z-[9999] w-80 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700"
                    :style="calendarPosition"
                >
                <!-- Calendar Header -->
                <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                    <button
                        @click="previousMonth"
                        type="button"
                        class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"
                    >
                        <ChevronLeftIcon class="h-5 w-5" />
                    </button>
                    
                    <div class="flex items-center space-x-2">
                        <select
                            v-model="currentMonth"
                            class="text-sm font-medium bg-transparent border-none focus:ring-0 text-gray-900 dark:text-gray-100"
                        >
                            <option v-for="(month, index) in monthNames" :key="index" :value="index">
                                {{ month }}
                            </option>
                        </select>
                        
                        <select
                            v-model="currentYear"
                            class="text-sm font-medium bg-transparent border-none focus:ring-0 text-gray-900 dark:text-gray-100"
                        >
                            <option v-for="year in yearRange" :key="year" :value="year">
                                {{ year }}
                            </option>
                        </select>
                    </div>
                    
                    <button
                        @click="nextMonth"
                        type="button"
                        class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"
                    >
                        <ChevronRightIcon class="h-5 w-5" />
                    </button>
                </div>

                <!-- Calendar Grid -->
                <div class="p-4">
                    <!-- Day Headers -->
                    <div class="grid grid-cols-7 gap-1 mb-2">
                        <div
                            v-for="day in dayNames"
                            :key="day"
                            class="h-8 flex items-center justify-center text-xs font-medium text-gray-500 dark:text-gray-400"
                        >
                            {{ day }}
                        </div>
                    </div>

                    <!-- Calendar Days -->
                    <div class="grid grid-cols-7 gap-1">
                        <button
                            v-for="date in calendarDays"
                            :key="date.key"
                            @click="selectDate(date)"
                            type="button"
                            class="h-8 flex items-center justify-center text-sm rounded-md"
                            :class="[
                                date.isCurrentMonth 
                                    ? 'text-gray-900 dark:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700'
                                    : 'text-gray-400 dark:text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-700/50',
                                date.isSelected 
                                    ? 'bg-indigo-600 text-white hover:bg-indigo-700' 
                                    : '',
                                date.isToday && !date.isSelected 
                                    ? 'bg-indigo-100 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400 font-semibold' 
                                    : '',
                                isDateDisabled(date) 
                                    ? 'cursor-not-allowed opacity-50' 
                                    : 'cursor-pointer'
                            ]"
                            :disabled="isDateDisabled(date)"
                        >
                            {{ date.day }}
                        </button>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="flex items-center justify-between px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-750 rounded-b-lg">
                    <button
                        @click="selectToday"
                        type="button"
                        class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 font-medium"
                    >
                        Today
                    </button>
                    <button
                        @click="closeCalendar"
                        type="button"
                        class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300"
                    >
                        Close
                    </button>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Error Message -->
        <p v-if="error" class="mt-2 text-sm text-red-600 dark:text-red-400">
            {{ error }}
        </p>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { 
    CalendarDaysIcon, 
    ChevronLeftIcon, 
    ChevronRightIcon, 
    XMarkIcon 
} from '@heroicons/vue/24/outline';

const props = defineProps({
    modelValue: {
        type: [String, Date],
        default: null
    },
    id: {
        type: String,
        default: () => `datepicker-${Math.random().toString(36).substr(2, 9)}`
    },
    placeholder: {
        type: String,
        default: 'Select date...'
    },
    disabled: {
        type: Boolean,
        default: false
    },
    error: {
        type: String,
        default: null
    },
    minDate: {
        type: [String, Date],
        default: null
    },
    maxDate: {
        type: [String, Date],
        default: null
    },
    format: {
        type: String,
        default: 'MMM d, yyyy'
    }
});

const emit = defineEmits(['update:modelValue']);

const input = ref(null);
const calendar = ref(null);
const showCalendar = ref(false);
const currentMonth = ref(new Date().getMonth());
const currentYear = ref(new Date().getFullYear());
const calendarPosition = ref({});

const monthNames = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

const dayNames = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

// Generate year range (10 years before and after current year)
const yearRange = computed(() => {
    const currentYear = new Date().getFullYear();
    const years = [];
    for (let year = currentYear - 10; year <= currentYear + 10; year++) {
        years.push(year);
    }
    return years;
});

// Format date for display
const displayValue = computed(() => {
    if (!props.modelValue) return '';
    const date = new Date(props.modelValue);
    if (isNaN(date.getTime())) return '';
    
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
});

// Get selected date object
const selectedDate = computed(() => {
    if (!props.modelValue) return null;
    const date = new Date(props.modelValue);
    return isNaN(date.getTime()) ? null : date;
});

// Generate calendar days for current month view
const calendarDays = computed(() => {
    const firstDay = new Date(currentYear.value, currentMonth.value, 1);
    const lastDay = new Date(currentYear.value, currentMonth.value + 1, 0);
    const startDate = new Date(firstDay);
    startDate.setDate(startDate.getDate() - firstDay.getDay());
    
    const days = [];
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    for (let i = 0; i < 42; i++) {
        const date = new Date(startDate);
        date.setDate(startDate.getDate() + i);
        
        const dateString = date.toISOString().split('T')[0];
        const isCurrentMonth = date.getMonth() === currentMonth.value;
        const isSelected = selectedDate.value && 
            date.getTime() === selectedDate.value.getTime();
        const isToday = date.getTime() === today.getTime();

        days.push({
            date,
            day: date.getDate(),
            dateString,
            isCurrentMonth,
            isSelected,
            isToday,
            key: `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`
        });
    }
    
    return days;
});

// Check if date is disabled based on min/max constraints
const isDateDisabled = (dateObj) => {
    if (props.disabled) return true;
    
    const date = dateObj.date;
    
    if (props.minDate) {
        const min = new Date(props.minDate);
        min.setHours(0, 0, 0, 0);
        if (date < min) return true;
    }
    
    if (props.maxDate) {
        const max = new Date(props.maxDate);
        max.setHours(23, 59, 59, 999);
        if (date > max) return true;
    }
    
    return false;
};

// Calculate calendar position
const calculatePosition = () => {
    if (!input.value) return;
    
    const inputRect = input.value.getBoundingClientRect();
    const viewportHeight = window.innerHeight;
    const viewportWidth = window.innerWidth;
    const calendarHeight = 400; // Approximate height of calendar
    const calendarWidth = 320; // Width of calendar (w-80 = 20rem = 320px)
    
    let top = inputRect.bottom + window.scrollY + 4; // 4px gap
    let left = inputRect.left + window.scrollX;
    
    // Check if calendar would go below viewport
    if (inputRect.bottom + calendarHeight > viewportHeight) {
        // Position above input instead
        top = inputRect.top + window.scrollY - calendarHeight - 4;
    }
    
    // Check if calendar would go beyond right edge
    if (inputRect.left + calendarWidth > viewportWidth) {
        // Align to right edge of input
        left = inputRect.right + window.scrollX - calendarWidth;
    }
    
    // Ensure calendar doesn't go beyond left edge
    if (left < 0) {
        left = 8; // Small margin from edge
    }
    
    calendarPosition.value = {
        top: `${top}px`,
        left: `${left}px`
    };
};

// Toggle calendar visibility
const toggleCalendar = () => {
    if (props.disabled) return;
    showCalendar.value = !showCalendar.value;
    
    if (showCalendar.value) {
        if (selectedDate.value) {
            currentMonth.value = selectedDate.value.getMonth();
            currentYear.value = selectedDate.value.getFullYear();
        }
        
        nextTick(() => {
            calculatePosition();
            document.addEventListener('click', handleOutsideClick);
            window.addEventListener('scroll', calculatePosition, true);
            window.addEventListener('resize', calculatePosition);
        });
    } else {
        document.removeEventListener('click', handleOutsideClick);
        window.removeEventListener('scroll', calculatePosition, true);
        window.removeEventListener('resize', calculatePosition);
    }
};

// Close calendar
const closeCalendar = () => {
    showCalendar.value = false;
    document.removeEventListener('click', handleOutsideClick);
    window.removeEventListener('scroll', calculatePosition, true);
    window.removeEventListener('resize', calculatePosition);
};

// Handle clicks outside the calendar
const handleOutsideClick = (event) => {
    if (!calendar.value?.contains(event.target) && !input.value?.contains(event.target)) {
        closeCalendar();
    }
};

// Navigation methods
const previousMonth = () => {
    if (currentMonth.value === 0) {
        currentMonth.value = 11;
        currentYear.value--;
    } else {
        currentMonth.value--;
    }
};

const nextMonth = () => {
    if (currentMonth.value === 11) {
        currentMonth.value = 0;
        currentYear.value++;
    } else {
        currentMonth.value++;
    }
};

// Date selection methods
const selectDate = (dateObj) => {
    if (isDateDisabled(dateObj)) return;
    
    const date = dateObj.date;
    emit('update:modelValue', date.toISOString().split('T')[0]);
    closeCalendar();
};

const selectToday = () => {
    const today = new Date();
    if (isDateDisabled({ date: today })) return;
    
    emit('update:modelValue', today.toISOString().split('T')[0]);
    closeCalendar();
};

const clearDate = () => {
    emit('update:modelValue', null);
};

// Watch for prop changes
watch(() => props.modelValue, (newValue) => {
    if (newValue && selectedDate.value) {
        currentMonth.value = selectedDate.value.getMonth();
        currentYear.value = selectedDate.value.getFullYear();
    }
});

// Cleanup
onUnmounted(() => {
    document.removeEventListener('click', handleOutsideClick);
    window.removeEventListener('scroll', calculatePosition, true);
    window.removeEventListener('resize', calculatePosition);
});
</script>

<style scoped>
/* Custom styles for select dropdowns in calendar header */
select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    padding-right: 2.5rem;
}

.dark select {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%9ca3af' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
}
</style>