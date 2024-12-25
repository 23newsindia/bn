function macpClearPageCache(event) {
    event.preventDefault();
    
    if (!confirm('Are you sure you want to clear the cache for this page?')) {
        return;
    }

    jQuery.ajax({
        url: macpAdmin.ajaxUrl,
        type: 'POST',
        data: {
            action: 'macp_clear_page_cache',
            nonce: macpAdmin.nonce,
            url: macpAdmin.currentUrl
        },
        success: function(response) {
            if (response.success) {
                alert('Page cache cleared successfully!');
            } else {
                alert('Failed to clear page cache: ' + response.data);
            }
        },
        error: function() {
            alert('Failed to clear page cache. Please try again.');
        }
    });
}