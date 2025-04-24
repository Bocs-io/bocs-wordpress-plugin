/**
 * BOCS Cache Clearing Script
 * 
 * This script helps clear cached resources in the browser during development.
 * It adds a unique query parameter to JS and CSS resources that use the bocs_get_cache_bust_version function.
 */

(function() {
    'use strict';
    
    console.log('BOCS Cache Buster - Initialized');
    
    // Check if we're in developer mode
    const isDeveloperMode = (window.bocsData && window.bocsData.developerMode) || false;
    
    if (!isDeveloperMode) {
        console.log('BOCS Cache Buster - Developer mode is not enabled, exiting');
        return;
    }
    
    console.log('BOCS Cache Buster - Developer mode detected, proceeding with cache busting');
    
    // Function to clear local storage caches
    function clearLocalStorageCaches() {
        try {
            // List of known localStorage keys used by the application
            const cacheKeys = [
                'bocs_product_cache',
                'bocs_subscription_cache',
                'bocs_user_preferences'
            ];
            
            cacheKeys.forEach(key => {
                if (localStorage.getItem(key)) {
                    localStorage.removeItem(key);
                    console.log(`BOCS Cache Buster - Cleared localStorage cache: ${key}`);
                }
            });
        } catch (e) {
            console.error('BOCS Cache Buster - Error clearing localStorage:', e);
        }
    }
    
    // Clear localStorage caches
    clearLocalStorageCaches();
    
    // Add a reload button to the admin bar if available
    function addCacheBustButton() {
        const adminBar = document.getElementById('wpadminbar');
        if (!adminBar) return;
        
        const adminBarList = adminBar.querySelector('#wp-admin-bar-root-default');
        if (!adminBarList) return;
        
        // Create new admin bar item
        const li = document.createElement('li');
        li.id = 'wp-admin-bar-bocs-cache-clear';
        li.className = 'menupop';
        
        // Create button
        const a = document.createElement('a');
        a.className = 'ab-item';
        a.href = '#';
        a.innerHTML = 'BOCS: Clear Cache';
        
        // Add click event
        a.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Clear localStorage
            clearLocalStorageCaches();
            
            // Reload the page with a cache busting parameter
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('bocs_cache_bust', Date.now());
            window.location.href = currentUrl.toString();
        });
        
        li.appendChild(a);
        adminBarList.appendChild(li);
        
        console.log('BOCS Cache Buster - Added cache clear button to admin bar');
    }
    
    // Add the button once the DOM is fully loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addCacheBustButton);
    } else {
        addCacheBustButton();
    }
    
})(); 