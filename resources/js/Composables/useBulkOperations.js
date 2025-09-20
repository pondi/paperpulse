import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

export function useBulkOperations(items, options = {}) {
    const {
        idField = 'id',
        preserveState = true,
        preserveScroll = false
    } = options

    const selectedItems = ref([])
    const isProcessing = ref(false)

    const allSelected = computed(() => {
        if (!items.value || items.value.length === 0) return false
        return selectedItems.value.length === items.value.length
    })

    const someSelected = computed(() => {
        return selectedItems.value.length > 0 && selectedItems.value.length < (items.value?.length || 0)
    })

    const hasSelection = computed(() => {
        return selectedItems.value.length > 0
    })

    const selectedCount = computed(() => {
        return selectedItems.value.length
    })

    const toggleSelectAll = () => {
        if (!items.value) return

        if (allSelected.value) {
            selectedItems.value = []
        } else {
            selectedItems.value = items.value.map(item => item[idField])
        }
    }

    const toggleSelect = (itemId) => {
        const index = selectedItems.value.indexOf(itemId)
        if (index > -1) {
            selectedItems.value.splice(index, 1)
        } else {
            selectedItems.value.push(itemId)
        }
    }

    const isSelected = (itemId) => {
        return selectedItems.value.includes(itemId)
    }

    const performBulkAction = (action, routeName, additionalData = {}) => {
        if (!hasSelection.value) return

        isProcessing.value = true

        const data = {
            ...additionalData,
            ids: selectedItems.value,
            action
        }

        router.post(route(routeName), data, {
            preserveState,
            preserveScroll,
            onSuccess: () => {
                selectedItems.value = []
            },
            onFinish: () => {
                isProcessing.value = false
            }
        })
    }

    const performBulkDelete = (routeName, confirmMessage = 'Are you sure you want to delete the selected items?') => {
        if (!hasSelection.value) return false
        
        if (!confirm(confirmMessage)) return false

        performBulkAction('delete', routeName)
        return true
    }

    const clearSelection = () => {
        selectedItems.value = []
    }

    const selectItems = (itemIds) => {
        selectedItems.value = Array.isArray(itemIds) ? [...itemIds] : [itemIds]
    }

    const getSelectedItems = () => {
        if (!items.value) return []
        
        return items.value.filter(item => selectedItems.value.includes(item[idField]))
    }

    return {
        selectedItems,
        allSelected,
        someSelected,
        hasSelection,
        selectedCount,
        isProcessing,
        toggleSelectAll,
        toggleSelect,
        isSelected,
        performBulkAction,
        performBulkDelete,
        clearSelection,
        selectItems,
        getSelectedItems
    }
}