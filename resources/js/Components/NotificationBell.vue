<template>
  <Menu as="div" class="relative ml-3">
    <div>
      <MenuButton class="relative flex rounded-full bg-gray-800 text-sm focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-800">
        <span class="absolute -inset-1.5" />
        <span class="sr-only">View notifications</span>
        <BellIcon class="h-6 w-6 text-gray-400 hover:text-gray-300" aria-hidden="true" />
        <span v-if="unreadCount > 0" class="absolute -top-1 -right-1 h-5 w-5 rounded-full bg-red-500 flex items-center justify-center">
          <span class="text-xs text-white font-medium">{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
        </span>
      </MenuButton>
    </div>
    <transition
      enter-active-class="transition ease-out duration-100"
      enter-from-class="transform opacity-0 scale-95"
      enter-to-class="transform opacity-100 scale-100"
      leave-active-class="transition ease-in duration-75"
      leave-from-class="transform opacity-100 scale-100"
      leave-to-class="transform opacity-0 scale-95"
    >
      <MenuItems class="absolute right-0 z-10 mt-2 w-96 origin-top-right rounded-md bg-white dark:bg-gray-800 py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
        <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('notifications') }}</h3>
            <div class="flex items-center space-x-2">
              <button
                v-if="unreadCount > 0"
                @click="markAllAsRead"
                class="text-xs text-indigo-600 hover:text-indigo-500"
              >
                {{ __('mark_all_read') }}
              </button>
              <button
                v-if="notifications.length > 0"
                @click="clearAll"
                class="text-xs text-gray-500 hover:text-gray-700"
              >
                {{ __('clear_all') }}
              </button>
            </div>
          </div>
        </div>
        
        <div v-if="loading" class="px-4 py-8 text-center">
          <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
        </div>
        
        <div v-else-if="notifications.length === 0" class="px-4 py-8 text-center">
          <BellSlashIcon class="mx-auto h-12 w-12 text-gray-400" />
          <p class="mt-2 text-sm text-gray-500">{{ __('no_notifications') }}</p>
        </div>
        
        <div v-else class="max-h-96 overflow-y-auto">
          <MenuItem v-for="notification in notifications" :key="notification.id" v-slot="{ active }">
            <div
              @click="handleNotificationClick(notification)"
              :class="[
                active ? 'bg-gray-100 dark:bg-gray-700' : '',
                !notification.read_at ? 'bg-blue-50 dark:bg-blue-900/20' : '',
                'block px-4 py-3 cursor-pointer border-b border-gray-100 dark:border-gray-700 last:border-0'
              ]"
            >
              <div class="flex items-start">
                <div class="flex-shrink-0">
                  <component :is="getNotificationIcon(notification)" class="h-6 w-6" :class="getNotificationIconClass(notification)" />
                </div>
                <div class="ml-3 flex-1">
                  <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                    {{ getNotificationTitle(notification) }}
                  </p>
                  <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ getNotificationMessage(notification) }}
                  </p>
                  <p class="mt-1 text-xs text-gray-400">
                    {{ formatTime(notification.created_at) }}
                  </p>
                </div>
                <button
                  @click.stop="deleteNotification(notification.id)"
                  class="ml-2 text-gray-400 hover:text-gray-600"
                >
                  <XMarkIcon class="h-4 w-4" />
                </button>
              </div>
            </div>
          </MenuItem>
        </div>
        
        <div v-if="notifications.length > 0" class="px-4 py-2 border-t border-gray-200 dark:border-gray-700">
          <Link
            :href="route('preferences.index')"
            class="text-sm text-indigo-600 hover:text-indigo-500"
          >
            {{ __('notification_settings') }}
          </Link>
        </div>
      </MenuItems>
    </transition>
  </Menu>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue';
import {
  BellIcon,
  BellSlashIcon,
  CheckCircleIcon,
  XCircleIcon,
  DocumentDuplicateIcon,
  FolderIcon,
  CloudArrowDownIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline';
import axios from 'axios';

const loading = ref(false);
const notifications = ref([]);
const unreadCount = ref(0);
let pollInterval = null;

const __ = (key) => {
  const messages = window.page?.props?.language?.messages || {};
  return messages[key] || key;
};

const loadNotifications = async () => {
  try {
    const response = await axios.get('/notifications');
    notifications.value = response.data.notifications;
    unreadCount.value = response.data.unread_count;
  } catch (error) {
    console.error('Failed to load notifications:', error);
  }
};

const markAsRead = async (notificationId) => {
  try {
    await axios.post(`/notifications/${notificationId}/read`);
    const notification = notifications.value.find(n => n.id === notificationId);
    if (notification) {
      notification.read_at = new Date().toISOString();
      unreadCount.value = Math.max(0, unreadCount.value - 1);
    }
  } catch (error) {
    console.error('Failed to mark notification as read:', error);
  }
};

const markAllAsRead = async () => {
  try {
    await axios.post('/notifications/read-all');
    notifications.value.forEach(n => {
      if (!n.read_at) {
        n.read_at = new Date().toISOString();
      }
    });
    unreadCount.value = 0;
  } catch (error) {
    console.error('Failed to mark all notifications as read:', error);
  }
};

const deleteNotification = async (notificationId) => {
  try {
    await axios.delete(`/notifications/${notificationId}`);
    notifications.value = notifications.value.filter(n => n.id !== notificationId);
    if (notifications.value.find(n => n.id === notificationId && !n.read_at)) {
      unreadCount.value = Math.max(0, unreadCount.value - 1);
    }
  } catch (error) {
    console.error('Failed to delete notification:', error);
  }
};

const clearAll = async () => {
  if (!confirm(__('clear_all_notifications_confirm'))) return;
  
  try {
    await axios.post('/notifications/clear');
    notifications.value = [];
    unreadCount.value = 0;
  } catch (error) {
    console.error('Failed to clear notifications:', error);
  }
};

const handleNotificationClick = (notification) => {
  if (!notification.read_at) {
    markAsRead(notification.id);
  }
  
  // Navigate based on notification type
  if (notification.data.type === 'receipt_processed' && notification.data.receipt_id) {
    router.visit(route('receipts.show', notification.data.receipt_id));
  } else if (notification.data.type === 'scanner_files_imported') {
    router.visit(route('pulsedav.index'));
  } else if (notification.data.type === 'bulk_operation_completed') {
    router.visit(route('receipts.index'));
  }
};

const getNotificationIcon = (notification) => {
  const type = notification.data.type;
  if (type === 'receipt_processed') return CheckCircleIcon;
  if (type === 'receipt_failed') return XCircleIcon;
  if (type === 'bulk_operation_completed') return FolderIcon;
  if (type === 'scanner_files_imported') return CloudArrowDownIcon;
  return BellIcon;
};

const getNotificationIconClass = (notification) => {
  const type = notification.data.type;
  if (type === 'receipt_processed') return 'text-green-500';
  if (type === 'receipt_failed') return 'text-red-500';
  if (type === 'bulk_operation_completed') return 'text-indigo-500';
  if (type === 'scanner_files_imported') return 'text-blue-500';
  return 'text-gray-400';
};

const getNotificationTitle = (notification) => {
  const type = notification.data.type;
  if (type === 'receipt_processed') return __('receipt_processed');
  if (type === 'receipt_failed') return __('receipt_processing_failed');
  if (type === 'bulk_operation_completed') return __('bulk_operation_completed');
  if (type === 'scanner_files_imported') return __('scanner_files_imported');
  return __('notification');
};

const getNotificationMessage = (notification) => {
  const data = notification.data;
  
  if (data.type === 'receipt_processed') {
    return `${data.merchant_name} - ${formatCurrency(data.amount, data.currency)}`;
  }
  
  if (data.type === 'receipt_failed') {
    return data.error_message || __('processing_error');
  }
  
  if (data.type === 'bulk_operation_completed') {
    const operation = data.operation;
    const count = data.count;
    if (operation === 'delete') return `${count} ${__('receipts_deleted')}`;
    if (operation === 'categorize') return `${count} ${__('receipts_categorized')}`;
    if (operation === 'export') return `${count} ${__('receipts_exported')}`;
    return `${count} ${__('items_processed')}`;
  }
  
  if (data.type === 'scanner_files_imported') {
    return `${data.file_count} ${__('files_imported')}`;
  }
  
  return '';
};

const formatTime = (timestamp) => {
  const date = new Date(timestamp);
  const now = new Date();
  const diff = Math.floor((now - date) / 1000);
  
  if (diff < 60) return __('just_now');
  if (diff < 3600) return `${Math.floor(diff / 60)} ${__('minutes_ago')}`;
  if (diff < 86400) return `${Math.floor(diff / 3600)} ${__('hours_ago')}`;
  if (diff < 604800) return `${Math.floor(diff / 86400)} ${__('days_ago')}`;
  
  return date.toLocaleDateString();
};

const formatCurrency = (amount, currency) => {
  return new Intl.NumberFormat('nb-NO', {
    style: 'currency',
    currency: currency || 'NOK',
  }).format(amount || 0);
};

onMounted(() => {
  loading.value = true;
  loadNotifications().finally(() => {
    loading.value = false;
  });
  
  // Poll for new notifications every 30 seconds
  pollInterval = setInterval(loadNotifications, 30000);
  
  // Listen for Echo events if available
  if (window.Echo) {
    window.Echo.private(`App.Models.User.${window.page.props.auth.user.id}`)
      .notification(() => {
        loadNotifications();
      });
  }
});

onUnmounted(() => {
  if (pollInterval) {
    clearInterval(pollInterval);
  }
});
</script>