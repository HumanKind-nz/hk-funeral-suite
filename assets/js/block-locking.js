/**
 * Block locking script to prevent deletion of required blocks
 * 
 * @package    HK_Funeral_Suite
 * @subpackage Admin
 * @version    1.0.0
 */
(function() {
    const { subscribe, select, dispatch } = wp.data;
    const { createHigherOrderComponent } = wp.compose;
    const { addFilter } = wp.hooks;
    const { Fragment } = wp.element;
    const { InspectorControls } = wp.blockEditor;
    const { PanelBody, Notice } = wp.components;
    
    // The block name that should be protected
    const requiredBlockName = hkFsBlockLocking.requiredBlock;
    
    // Subscribe to block selection changes
    wp.domReady(function() {
        // This runs when the block editor loads
        subscribe(() => {
            // Get all blocks from the editor
            const blocks = select('core/block-editor').getBlocks();
            
            // Check if our required block exists
            let hasRequiredBlock = false;
            for (const block of blocks) {
                if (block.name === requiredBlockName) {
                    hasRequiredBlock = true;
                    break;
                }
            }
            
            // If required block doesn't exist, insert it at the beginning
            if (!hasRequiredBlock) {
                dispatch('core/block-editor').insertBlocks(
                    wp.blocks.createBlock(requiredBlockName),
                    0 // Insert at the beginning
                );
            }
        });
        
        // Listen for attempts to remove blocks
        const originalRemoveBlocks = dispatch('core/block-editor').removeBlocks;
        dispatch('core/block-editor').removeBlocks = function(blockIds) {
            const blocks = select('core/block-editor').getBlocksByClientId(blockIds);
            
            // Filter out any blocks that match our required block name
            const filteredBlockIds = blockIds.filter((id, index) => {
                return blocks[index]?.name !== requiredBlockName;
            });
            
            // If we filtered out any blocks, show a notice
            if (filteredBlockIds.length !== blockIds.length) {
                dispatch('core/notices').createNotice(
                    'warning',
                    'This block cannot be removed as it contains essential information.',
                    {
                        type: 'snackbar',
                        isDismissible: true,
                    }
                );
            }
            
            // Only remove blocks that aren't protected
            if (filteredBlockIds.length > 0) {
                originalRemoveBlocks(filteredBlockIds);
            }
        };
    });
    
    // Add notice to the required block's inspector controls
    const withInspectorControls = createHigherOrderComponent((BlockEdit) => {
        return (props) => {
            if (props.name !== requiredBlockName) {
                return <BlockEdit {...props} />;
            }
            
            return (
                <Fragment>
                    <BlockEdit {...props} />
                    <InspectorControls>
                        <PanelBody title="Block Information" initialOpen={true}>
                            <Notice status="warning" isDismissible={false}>
                                This block contains essential information and cannot be removed.
                            </Notice>
                        </PanelBody>
                    </InspectorControls>
                </Fragment>
            );
        };
    }, 'withInspectorControls');
    
    addFilter(
        'editor.BlockEdit',
        'hk-funeral-suite/with-inspector-controls',
        withInspectorControls
    );
})();
