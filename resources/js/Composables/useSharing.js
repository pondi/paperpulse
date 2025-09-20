import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

export function useSharing(options = {}) {
    const {
        preserveState = true,
        preserveScroll = false
    } = options

    const isSharing = ref(false)
    const isUnsharing = ref(false)
    const shareModalOpen = ref(false)
    const shares = ref([])

    const shareItem = (itemId, routeName, userData) => {
        isSharing.value = true

        router.post(route(routeName, { id: itemId }), userData, {
            preserveState,
            preserveScroll,
            onSuccess: () => {
                shareModalOpen.value = false
                // Refresh shares after successful share
                if (shares.value.length > 0) {
                    loadShares(itemId, routeName.replace('.share', '.shares'))
                }
            },
            onFinish: () => {
                isSharing.value = false
            }
        })
    }

    const unshareItem = (itemId, userId, routeName) => {
        isUnsharing.value = true

        router.delete(route(routeName, { id: itemId, user: userId }), {
            preserveState,
            preserveScroll,
            onSuccess: () => {
                // Remove from local shares array
                shares.value = shares.value.filter(share => share.shared_with_user_id !== userId)
            },
            onFinish: () => {
                isUnsharing.value = false
            }
        })
    }

    const loadShares = async (itemId, apiRouteName) => {
        try {
            const response = await fetch(route(apiRouteName, { id: itemId }))
            const data = await response.json()
            shares.value = data
        } catch (error) {
            console.error('Failed to load shares:', error)
            shares.value = []
        }
    }

    const openShareModal = () => {
        shareModalOpen.value = true
    }

    const closeShareModal = () => {
        shareModalOpen.value = false
    }

    const canShare = (item, user) => {
        if (!item || !user) return false
        
        // User can share if they own the item
        return item.user_id === user.id
    }

    const canUnshare = (share, user) => {
        if (!share || !user) return false
        
        // User can unshare if they are the owner who created the share
        return share.shared_by_user_id === user.id
    }

    const getSharePermissionText = (permission) => {
        const permissions = {
            'view': 'Can view',
            'edit': 'Can edit'
        }
        return permissions[permission] || permission
    }

    const validateEmail = (email) => {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
        return emailRegex.test(email)
    }

    const isAlreadyShared = (email) => {
        return shares.value.some(share => 
            share.shared_with_user?.email === email
        )
    }

    return {
        isSharing,
        isUnsharing,
        shareModalOpen,
        shares,
        shareItem,
        unshareItem,
        loadShares,
        openShareModal,
        closeShareModal,
        canShare,
        canUnshare,
        getSharePermissionText,
        validateEmail,
        isAlreadyShared
    }
}