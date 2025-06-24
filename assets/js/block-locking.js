/**
* Block locking script for HK Funeral Suite
* 
* Prevents deletion of required blocks in the Gutenberg editor.
* @package    HK_Funeral_Suite
* @subpackage Admin
* @version    1.0.5
*/
(function() {
 // The block name that should be protected from deletion
 const requiredBlockName = hkFsBlockLocking.requiredBlock;
 
 // Function to disable delete buttons in the block settings menu
 function disableDeleteButtons() {
     const mutationObserver = new MutationObserver(function(mutations) {
         // Look for the block settings menu popover
         const popover = document.querySelector('.block-editor-block-settings-menu__popover .components-popover__content');
         if (!popover) return;
         
         // Get the selected block
         const selectedBlock = wp.data.select('core/block-editor').getSelectedBlock();
         if (!selectedBlock) return;
         
         // Check if it's our required block
         if (selectedBlock.name === requiredBlockName) {
             // Find all menu items
             const menuItems = popover.querySelectorAll('button');
             menuItems.forEach(button => {
                 // Look for delete/remove buttons
                 if (button.textContent.includes('Delete') || 
                     button.textContent.includes('Remove') ||
                     button.getAttribute('aria-label') === 'Remove Block') {
                     
                     // Disable the button
                     button.disabled = true;
                     button.style.opacity = '0.5';
                     button.style.cursor = 'not-allowed';
                 }
             });
         }
     });
     
     // Start observing the document for changes
     mutationObserver.observe(document.body, {
         childList: true,
         subtree: true
     });
 }
 
 // Restore required blocks if they get deleted somehow
 function setupBlockRestoration() {
     const { subscribe, select, dispatch } = wp.data;
     let lastBlocks = [];
     
     subscribe(() => {
         const currentBlocks = select('core/block-editor').getBlocks();
         
         // Check if our required block was in the last state but is missing now
         const hadRequiredBlock = lastBlocks.some(block => block.name === requiredBlockName);
         const hasRequiredBlock = currentBlocks.some(block => block.name === requiredBlockName);
         
         if (hadRequiredBlock && !hasRequiredBlock) {
             // Get the required block that was deleted
             const deletedBlock = lastBlocks.find(block => block.name === requiredBlockName);
             
             // Re-insert it at the beginning
             dispatch('core/block-editor').insertBlocks(
                 wp.blocks.createBlock(requiredBlockName, deletedBlock.attributes),
                 0
             );
             
             // Show error message
             dispatch('core/notices').createNotice(
                 'error',
                 'This block contains required information and cannot be removed.',
                 {
                     type: 'snackbar',
                     isDismissible: true,
                 }
             );
         }
         
         // Save the current state for next comparison
         lastBlocks = [...currentBlocks];
     });
 }
 
 // Override the core block editor removal functions
 function overrideRemoveFunctions() {
     const { select, dispatch } = wp.data;
     
     try {
         // Get the original functions
         const originalRemoveBlock = dispatch('core/block-editor').removeBlock;
         const originalRemoveBlocks = dispatch('core/block-editor').removeBlocks;
         
         // Replace removeBlock
         dispatch('core/block-editor').removeBlock = function(blockId) {
             const block = select('core/block-editor').getBlock(blockId);
             
             if (block && block.name === requiredBlockName) {
                 dispatch('core/notices').createNotice(
                     'error',
                     'This block contains required information and cannot be removed.',
                     {
                         type: 'snackbar',
                         isDismissible: true,
                     }
                 );
                 return;
             }
             
             originalRemoveBlock(blockId);
         };
         
         // Replace removeBlocks
         dispatch('core/block-editor').removeBlocks = function(blockIds) {
             const nonRequiredIds = blockIds.filter(id => {
                 const block = select('core/block-editor').getBlock(id);
                 return !(block && block.name === requiredBlockName);
             });
             
             if (nonRequiredIds.length !== blockIds.length) {
                 dispatch('core/notices').createNotice(
                     'error',
                     'Cannot remove blocks containing required information.',
                     {
                         type: 'snackbar',
                         isDismissible: true,
                     }
                 );
             }
             
             if (nonRequiredIds.length > 0) {
                 originalRemoveBlocks(nonRequiredIds);
             }
         };
     } catch (e) {
         console.error('Failed to override remove functions:', e);
     }
 }
 
 // Initialize when DOM is ready
 wp.domReady(function() {
     disableDeleteButtons();
     setupBlockRestoration();
     overrideRemoveFunctions();
     
     console.log('HK Funeral Suite: Block deletion prevention active');
 });
})();
