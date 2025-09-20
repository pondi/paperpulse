import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { debounce } from 'lodash'

export function useSearch(initialSearch = '', routeName = null, options = {}) {
    const {
        delay = 300,
        preserveState = true,
        preserveScroll = true,
        onlyKeys = null
    } = options

    const search = ref(initialSearch)
    const isSearching = ref(false)

    const performSearch = debounce((searchTerm) => {
        if (!routeName) return

        isSearching.value = true

        const searchParams = { search: searchTerm }
        
        // If onlyKeys is provided, include only those keys from current URL params
        if (onlyKeys && Array.isArray(onlyKeys)) {
            const currentParams = new URLSearchParams(window.location.search)
            onlyKeys.forEach(key => {
                if (currentParams.has(key)) {
                    searchParams[key] = currentParams.get(key)
                }
            })
        }

        router.get(route(routeName), searchParams, {
            preserveState,
            preserveScroll,
            onFinish: () => {
                isSearching.value = false
            }
        })
    }, delay)

    // Watch for search changes
    watch(search, (newSearch) => {
        performSearch(newSearch)
    })

    const clearSearch = () => {
        search.value = ''
    }

    return {
        search,
        isSearching,
        performSearch: (searchTerm) => performSearch(searchTerm),
        clearSearch
    }
}