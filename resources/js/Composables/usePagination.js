import { router } from '@inertiajs/vue3'

export function usePagination(routeName = null, options = {}) {
    const {
        preserveState = true,
        preserveScroll = true,
        onlyKeys = null
    } = options

    const getCurrentParams = () => {
        const currentParams = new URLSearchParams(window.location.search)
        const params = {}
        
        if (onlyKeys && Array.isArray(onlyKeys)) {
            onlyKeys.forEach(key => {
                if (currentParams.has(key)) {
                    params[key] = currentParams.get(key)
                }
            })
        } else {
            // Include all current params except page and per_page
            for (const [key, value] of currentParams.entries()) {
                if (!['page', 'per_page'].includes(key)) {
                    params[key] = value
                }
            }
        }
        
        return params
    }

    const goToPage = (page, customRouteName = null) => {
        const targetRoute = customRouteName || routeName
        if (!targetRoute) return

        const params = {
            ...getCurrentParams(),
            page
        }

        router.get(route(targetRoute), params, {
            preserveState,
            preserveScroll
        })
    }

    const changePerPage = (perPage, customRouteName = null) => {
        const targetRoute = customRouteName || routeName
        if (!targetRoute) return

        const params = {
            ...getCurrentParams(),
            per_page: perPage,
            page: 1 // Reset to first page when changing per page
        }

        router.get(route(targetRoute), params, {
            preserveState,
            preserveScroll
        })
    }

    const goToFirst = (customRouteName = null) => {
        goToPage(1, customRouteName)
    }

    const goToLast = (lastPage, customRouteName = null) => {
        goToPage(lastPage, customRouteName)
    }

    const goToNext = (currentPage, customRouteName = null) => {
        goToPage(currentPage + 1, customRouteName)
    }

    const goToPrev = (currentPage, customRouteName = null) => {
        if (currentPage > 1) {
            goToPage(currentPage - 1, customRouteName)
        }
    }

    return {
        goToPage,
        changePerPage,
        goToFirst,
        goToLast,
        goToNext,
        goToPrev
    }
}