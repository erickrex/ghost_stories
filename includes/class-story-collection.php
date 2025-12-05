<?php
/**
 * Story Collection class
 *
 * Manages collections of multiple stories with filtering and querying capabilities
 *
 * @package WP_Stories_Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Story Collection class
 */
class Story_Collection implements ArrayAccess, IteratorAggregate {
    
    /**
     * Array of WP_Story objects
     *
     * @var WP_Story[]
     */
    private $stories = array();
    
    /**
     * Collection metadata
     *
     * @var array
     */
    private $metadata = array();
    
    /**
     * Constructor
     *
     * @param array $story_ids Optional array of story post IDs to initialize with
     */
    public function __construct($story_ids = array()) {
        $this->metadata = array(
            'created_at' => current_time('timestamp'),
            'total_count' => 0,
            'active_count' => 0,
            'expired_count' => 0,
        );
        
        if (!empty($story_ids)) {
            $this->load_stories($story_ids);
        }
    }
    
    /**
     * Load stories from post IDs
     *
     * @param array $story_ids Array of story post IDs
     */
    private function load_stories($story_ids) {
        foreach ($story_ids as $story_id) {
            try {
                $story = new WP_Story($story_id);
                $this->stories[] = $story;
            } catch (Exception $e) {
                // Skip invalid stories but log the error
                error_log('WP Stories Plugin: Invalid story ' . $story_id . ' in collection: ' . $e->getMessage());
            }
        }
        
        $this->update_metadata();
    }
    
    /**
     * Add story to collection
     *
     * @param WP_Story|int $story WP_Story object or post ID
     * @return bool True on success, false on failure
     */
    public function add_story($story) {
        try {
            if (is_numeric($story)) {
                $story = new WP_Story($story);
            }
            
            if (!($story instanceof WP_Story)) {
                return false;
            }
            
            // Check if story already exists in collection
            foreach ($this->stories as $existing_story) {
                if ($existing_story->get_id() === $story->get_id()) {
                    return false; // Story already exists
                }
            }
            
            $this->stories[] = $story;
            $this->update_metadata();
            
            return true;
        } catch (Exception $e) {
            error_log('WP Stories Plugin: Failed to add story to collection: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remove story from collection
     *
     * @param int $story_id Story post ID
     * @return bool True on success, false on failure
     */
    public function remove_story($story_id) {
        $initial_count = count($this->stories);
        
        $this->stories = array_filter($this->stories, function($story) use ($story_id) {
            return $story->get_id() !== $story_id;
        });
        
        // Re-index array
        $this->stories = array_values($this->stories);
        
        if (count($this->stories) < $initial_count) {
            $this->update_metadata();
            return true;
        }
        
        return false;
    }
    
    /**
     * Get all stories in collection
     *
     * @return WP_Story[]
     */
    public function get_stories() {
        return $this->stories;
    }
    
    /**
     * Get active (non-expired) stories
     *
     * @return WP_Story[]
     */
    public function get_active_stories() {
        return array_filter($this->stories, function($story) {
            return !$story->is_expired();
        });
    }
    
    /**
     * Get expired stories
     *
     * @return WP_Story[]
     */
    public function get_expired_stories() {
        return array_filter($this->stories, function($story) {
            return $story->is_expired();
        });
    }
    
    /**
     * Get stories with valid media
     *
     * @return WP_Story[]
     */
    public function get_stories_with_media() {
        return array_filter($this->stories, function($story) {
            return $story->has_valid_media();
        });
    }
    
    /**
     * Filter expired stories from collection
     *
     * @return Story_Collection New collection with only active stories
     */
    public function filter_expired() {
        $active_stories = $this->get_active_stories();
        $new_collection = new self();
        
        foreach ($active_stories as $story) {
            $new_collection->add_story($story);
        }
        
        return $new_collection;
    }
    
    /**
     * Get stories for Gutenberg block
     *
     * @param array $story_ids Array of specific story IDs to include
     * @return Story_Collection New collection with specified stories
     */
    public function get_for_block($story_ids) {
        if (empty($story_ids)) {
            return new self();
        }
        
        $block_stories = array();
        
        // Get stories in the specified order
        foreach ($story_ids as $story_id) {
            foreach ($this->stories as $story) {
                if ($story->get_id() === intval($story_id)) {
                    $block_stories[] = $story;
                    break;
                }
            }
        }
        
        $new_collection = new self();
        foreach ($block_stories as $story) {
            $new_collection->add_story($story);
        }
        
        return $new_collection;
    }
    
    /**
     * Sort stories by date
     *
     * @param string $order 'ASC' or 'DESC'
     * @return Story_Collection New sorted collection
     */
    public function sort_by_date($order = 'DESC') {
        $sorted_stories = $this->stories;
        
        usort($sorted_stories, function($a, $b) use ($order) {
            $timestamp_a = $a->get_created_timestamp() ?: 0;
            $timestamp_b = $b->get_created_timestamp() ?: 0;
            
            if ($order === 'ASC') {
                return $timestamp_a - $timestamp_b;
            } else {
                return $timestamp_b - $timestamp_a;
            }
        });
        
        $new_collection = new self();
        foreach ($sorted_stories as $story) {
            $new_collection->add_story($story);
        }
        
        return $new_collection;
    }
    
    /**
     * Sort stories by expiration time
     *
     * @param string $order 'ASC' or 'DESC'
     * @return Story_Collection New sorted collection
     */
    public function sort_by_expiration($order = 'ASC') {
        $sorted_stories = $this->stories;
        
        usort($sorted_stories, function($a, $b) use ($order) {
            $time_a = $a->get_time_remaining();
            $time_b = $b->get_time_remaining();
            
            // Stories without expiration go to the end
            if ($time_a === null && $time_b === null) return 0;
            if ($time_a === null) return 1;
            if ($time_b === null) return -1;
            
            if ($order === 'ASC') {
                return $time_a - $time_b;
            } else {
                return $time_b - $time_a;
            }
        });
        
        $new_collection = new self();
        foreach ($sorted_stories as $story) {
            $new_collection->add_story($story);
        }
        
        return $new_collection;
    }
    
    /**
     * Get story count
     *
     * @return int
     */
    public function count() {
        return count($this->stories);
    }
    
    /**
     * Get active story count
     *
     * @return int
     */
    public function count_active() {
        return count($this->get_active_stories());
    }
    
    /**
     * Get expired story count
     *
     * @return int
     */
    public function count_expired() {
        return count($this->get_expired_stories());
    }
    
    /**
     * Check if collection is empty
     *
     * @return bool
     */
    public function is_empty() {
        return empty($this->stories);
    }
    
    /**
     * Check if collection has active stories
     *
     * @return bool
     */
    public function has_active_stories() {
        return $this->count_active() > 0;
    }
    
    /**
     * Get story by ID
     *
     * @param int $story_id Story post ID
     * @return WP_Story|null
     */
    public function get_story_by_id($story_id) {
        foreach ($this->stories as $story) {
            if ($story->get_id() === $story_id) {
                return $story;
            }
        }
        
        return null;
    }
    
    /**
     * Get story IDs
     *
     * @return array Array of story post IDs
     */
    public function get_story_ids() {
        return array_map(function($story) {
            return $story->get_id();
        }, $this->stories);
    }
    
    /**
     * Get active story IDs
     *
     * @return array Array of active story post IDs
     */
    public function get_active_story_ids() {
        return array_map(function($story) {
            return $story->get_id();
        }, $this->get_active_stories());
    }
    
    /**
     * Update collection metadata
     */
    private function update_metadata() {
        $this->metadata['total_count'] = $this->count();
        $this->metadata['active_count'] = $this->count_active();
        $this->metadata['expired_count'] = $this->count_expired();
        $this->metadata['updated_at'] = current_time('timestamp');
    }
    
    /**
     * Get collection metadata
     *
     * @return array
     */
    public function get_metadata() {
        return $this->metadata;
    }
    
    /**
     * Validate all stories in collection
     *
     * @return array Array of validation results keyed by story ID
     */
    public function validate_all() {
        $validation_results = array();
        
        foreach ($this->stories as $story) {
            $validation_results[$story->get_id()] = $story->validate();
        }
        
        return $validation_results;
    }
    
    /**
     * Get invalid stories
     *
     * @return WP_Story[]
     */
    public function get_invalid_stories() {
        return array_filter($this->stories, function($story) {
            return !$story->is_valid();
        });
    }
    
    /**
     * Remove invalid stories from collection
     *
     * @return int Number of stories removed
     */
    public function remove_invalid_stories() {
        $initial_count = $this->count();
        
        $this->stories = array_filter($this->stories, function($story) {
            return $story->is_valid();
        });
        
        // Re-index array
        $this->stories = array_values($this->stories);
        
        $removed_count = $initial_count - $this->count();
        
        if ($removed_count > 0) {
            $this->update_metadata();
        }
        
        return $removed_count;
    }
    
    /**
     * Get collection data as array (for JSON serialization)
     *
     * @param bool $include_expired Whether to include expired stories
     * @return array
     */
    public function to_array($include_expired = false) {
        $stories_data = array();
        
        $stories_to_include = $include_expired ? $this->stories : $this->get_active_stories();
        
        foreach ($stories_to_include as $story) {
            $stories_data[] = $story->to_array();
        }
        
        return array(
            'stories' => $stories_data,
            'metadata' => $this->get_metadata(),
            'counts' => array(
                'total' => $this->count(),
                'active' => $this->count_active(),
                'expired' => $this->count_expired(),
                'included' => count($stories_data),
            ),
        );
    }
    
    /**
     * Create collection from WordPress query
     *
     * @param WP_Query|array $query WP_Query object or query arguments
     * @return Story_Collection
     */
    public static function from_query($query) {
        if (is_array($query)) {
            $query = new WP_Query($query);
        }
        
        $collection = new self();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $collection->add_story(get_the_ID());
            }
            wp_reset_postdata();
        }
        
        return $collection;
    }
    
    /**
     * Create collection of all active stories
     *
     * @param array $args Additional query arguments
     * @return Story_Collection
     */
    public static function get_all_active($args = array()) {
        $query = Story_Post_Type::get_active_stories($args);
        return self::from_query($query);
    }
    
    /**
     * Create collection from specific story IDs
     *
     * @param array $story_ids Array of story post IDs
     * @return Story_Collection
     */
    public static function from_ids($story_ids) {
        return new self($story_ids);
    }
    
    /**
     * Iterator implementation - allows foreach loops
     *
     * @return ArrayIterator
     */
    #[\ReturnTypeWillChange]
    public function getIterator() {
        return new ArrayIterator($this->stories);
    }
    
    /**
     * ArrayAccess implementation - allows array-like access
     *
     * @param mixed $offset
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset) {
        return isset($this->stories[$offset]);
    }
    
    /**
     * ArrayAccess implementation
     *
     * @param mixed $offset
     * @return WP_Story|null
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset) {
        return isset($this->stories[$offset]) ? $this->stories[$offset] : null;
    }
    
    /**
     * ArrayAccess implementation
     *
     * @param mixed $offset
     * @param WP_Story $value
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value) {
        if ($value instanceof WP_Story) {
            if ($offset === null) {
                $this->stories[] = $value;
            } else {
                $this->stories[$offset] = $value;
            }
            $this->update_metadata();
        }
    }
    
    /**
     * ArrayAccess implementation
     *
     * @param mixed $offset
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset) {
        if (isset($this->stories[$offset])) {
            unset($this->stories[$offset]);
            $this->stories = array_values($this->stories); // Re-index
            $this->update_metadata();
        }
    }
}