/**
 * WordPress Stories Block - Main Registration
 * Vanilla JavaScript version (no build process required)
 */

(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { __ } = wp.i18n;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, SelectControl, CheckboxControl, Spinner } = wp.components;
    const { createElement: el, useState, useEffect } = wp.element;
    const { useSelect } = wp.data;
    const apiFetch = wp.apiFetch;

    /**
     * Edit Component
     */
    function Edit(props) {
        const { attributes, setAttributes } = props;
        const { selectedStories, alignment } = attributes;
        
        const [stories, setStories] = useState([]);
        const [loading, setLoading] = useState(true);
        
        const blockProps = useBlockProps({
            className: 'wp-stories-block-editor'
        });

        // Fetch available stories
        useEffect(function() {
            apiFetch({ path: '/wp/v2/wp_story?per_page=100&status=publish' })
                .then(function(response) {
                    setStories(response || []);
                    setLoading(false);
                })
                .catch(function(error) {
                    console.error('Error fetching stories:', error);
                    setLoading(false);
                });
        }, []);

        // Handle story selection toggle
        function toggleStory(storyId) {
            const isSelected = selectedStories.includes(storyId);
            let newSelection;
            
            if (isSelected) {
                newSelection = selectedStories.filter(function(id) { return id !== storyId; });
            } else {
                newSelection = selectedStories.concat([storyId]);
            }
            
            setAttributes({ selectedStories: newSelection });
        }

        // Handle alignment change
        function handleAlignmentChange(newAlignment) {
            setAttributes({ alignment: newAlignment });
        }

        return el('div', blockProps,
            // Inspector Controls (Sidebar)
            el(InspectorControls, null,
                el(PanelBody, { title: __('Story Selection', 'wp-stories-plugin'), initialOpen: true },
                    loading ? el(Spinner) : 
                    stories.length === 0 ? 
                        el('p', null, __('No stories found. Create a story first.', 'wp-stories-plugin')) :
                        el('div', { className: 'wp-stories-selector' },
                            el('p', { style: { marginBottom: '12px' } }, 
                                __('Select stories to display:', 'wp-stories-plugin')
                            ),
                            stories.map(function(story) {
                                return el(CheckboxControl, {
                                    key: story.id,
                                    label: story.title.rendered,
                                    checked: selectedStories.includes(story.id),
                                    onChange: function() { toggleStory(story.id); }
                                });
                            })
                        )
                ),
                el(PanelBody, { title: __('Layout Settings', 'wp-stories-plugin') },
                    el(SelectControl, {
                        label: __('Alignment', 'wp-stories-plugin'),
                        value: alignment,
                        options: [
                            { label: __('Left', 'wp-stories-plugin'), value: 'left' },
                            { label: __('Center', 'wp-stories-plugin'), value: 'center' },
                            { label: __('Right', 'wp-stories-plugin'), value: 'right' }
                        ],
                        onChange: handleAlignmentChange
                    })
                )
            ),
            
            // Block Preview
            el('div', { className: 'wp-stories-block-preview' },
                selectedStories.length === 0 ?
                    el('div', { className: 'wp-stories-empty-state' },
                        el('p', null, __('No stories selected. Use the sidebar to select stories.', 'wp-stories-plugin'))
                    ) :
                    el('div', { className: 'wp-stories-preview-circles', style: { textAlign: alignment } },
                        el('p', { style: { marginBottom: '12px', fontWeight: 'bold' } },
                            __('Selected Stories:', 'wp-stories-plugin') + ' ' + selectedStories.length
                        ),
                        el('div', { className: 'wp-stories-circles' },
                            selectedStories.map(function(storyId) {
                                const story = stories.find(function(s) { return s.id === storyId; });
                                return el('div', { 
                                    key: storyId, 
                                    className: 'wp-stories-circle-preview',
                                    style: { display: 'inline-block', margin: '0 8px' }
                                },
                                    el('div', { 
                                        className: 'wp-stories-circle-inner',
                                        style: {
                                            width: '80px',
                                            height: '80px',
                                            borderRadius: '50%',
                                            background: 'linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%)',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            color: 'white',
                                            fontSize: '12px',
                                            textAlign: 'center',
                                            padding: '4px'
                                        }
                                    },
                                        story ? story.title.rendered : __('Loading...', 'wp-stories-plugin')
                                    )
                                );
                            })
                        )
                    )
            )
        );
    }

    /**
     * Save Component
     */
    function Save(props) {
        const { attributes } = props;
        const { selectedStories, alignment } = attributes;
        
        const blockProps = useBlockProps.save({
            className: 'wp-stories-block wp-stories-align-' + alignment,
            'data-stories': JSON.stringify(selectedStories),
            'data-alignment': alignment
        });

        if (!selectedStories || selectedStories.length === 0) {
            return null;
        }

        return el('div', blockProps,
            el('div', { className: 'wp-stories-circles' },
                selectedStories.map(function(storyId) {
                    return el('div', {
                        key: storyId,
                        className: 'wp-stories-circle',
                        'data-story-id': storyId
                    },
                        el('div', { className: 'wp-stories-circle-inner' },
                            el('div', { className: 'wp-stories-circle-gradient' },
                                el('div', { className: 'wp-stories-circle-content' })
                            )
                        ),
                        el('span', { className: 'wp-stories-circle-label' })
                    );
                })
            )
        );
    }

    /**
     * Register the block
     */
    registerBlockType('wp-stories/stories-block', {
        apiVersion: 2,
        title: __('Stories', 'wp-stories-plugin'),
        description: __('Display Instagram-like stories with an interactive modal interface.', 'wp-stories-plugin'),
        category: 'media',
        icon: 'format-gallery',
        keywords: [
            __('stories', 'wp-stories-plugin'),
            __('media', 'wp-stories-plugin'),
            __('gallery', 'wp-stories-plugin')
        ],
        attributes: {
            selectedStories: {
                type: 'array',
                default: []
            },
            alignment: {
                type: 'string',
                default: 'left'
            }
        },
        supports: {
            align: ['left', 'center', 'right', 'wide', 'full'],
            html: false
        },
        edit: Edit,
        save: Save
    });

})(window.wp);