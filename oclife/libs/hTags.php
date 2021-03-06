<?php
/*
 * Copyright 2014 by Francesco PIRANEO G. (fpiraneo@gmail.com)
 *
 * This file is part of oclife.
 *
 * oclife is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * oclife is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with oclife.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Handle hierarchical structure of tags; in DB the structure of tag will be:
 *
 * @author fpiraneo
 */
namespace OCA\OCLife;
class hTags {
    /**
     * Returns the tag ID of an existing tag
     * @param type $tagLang language code (EN, IT, FR, ...)
     * @param type $tagDescr may be an array
     */
    public function searchTag($tagLang, $tagDescr) {
        $result = array();

        // *PREFIX* is being replaced with the ownCloud installation prefix
        $sql = "SELECT tagid FROM *PREFIX*oclife_humanReadable WHERE lang=? AND descr=?";
        $args = array($tagLang, $tagDescr);

        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);
        while($row = $resRsrc->fetchRow()) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * Search a tag from it's ID
     * @param integer $tagID
     * @return array Array containing language, description entries
     */
    public function searchTagFromID($tagID) {
        $result = array();

        // *PREFIX* is being replaced with the ownCloud installation prefix
        $sql = "SELECT lang, descr FROM *PREFIX*oclife_humanReadable WHERE tagid=?";
        $args = array($tagID);

        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);
        while($row = $resRsrc->fetchRow()) {
            $result[$row['lang']] = $row['descr'];
        }

        return $result;
    }

    /**
     * Create a new tag with the provided parameters - Return the tag ID
     * @param string $tagLang
     * @param string $tagDescr
     * @param integer $parentID Id of tag's parent
     * @return integer Newly inserted index, FALSE if parameters not valid
     */
    public function newTag($tagLang, $tagDescr, $parentID) {
        // Check if provided parameters are correct
        if(strlen(trim($tagLang)) !== 2 || trim($tagDescr) === '' || !is_int($parentID) || $parentID < -1) {
            return FALSE;
        }

        // Check if tag already exists
        if(count($this->searchTag($tagLang, $tagDescr)) != 0) {
            return FALSE;
        }

        // Proceed with creation
        $result = array();

        // Insert master record
        $sql = "INSERT INTO *PREFIX*oclife_tags (parent) VALUES (?)";
        $args = array($parentID);
        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);

        // Get inserted index
        $newIndex = \OCP\DB::insertid();

        // Insert human readable
        $args = array($newIndex, strtolower($tagLang), trim($tagDescr));
        $sql = "INSERT INTO *PREFIX*oclife_humanReadable (tagid, lang, descr) VALUES (?,?,?)";
        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);

        return $newIndex;
    }

    /**
     * Alter data of an existing tag; if 'tag language' - 'tag description' exist on DB
     * a data modification is performed; a data insertion otherwise;
     * if 'tag description' is empty a deletion occours.
     * @param integer $tagId ID of tag to be altered
     * @param array $tagData Array containing $tagLang=>$tagDescr couples
     * @return boolean
     */
    public function alterTag($tagId, $tagData) {
        if(!is_array($tagData)) {
            throw new Exception('Tag data must be an array.');
        }

        foreach($tagData as $tagLang => $tagDescr) {
            if(strlen(trim($tagLang)) != 2) {
                continue;
            }

            $tagLangToInsert = trim(strtolower($tagLang));
            $tagDescrToInsert = trim($tagDescr);

            // Check if we have to delete some data
            if($tagDescr === '') {
                $sql = 'DELETE FROM *PREFIX*oclife_humanReadable WHERE tagid=? AND lang=?';
                $args = array($tagId, $tagLangToInsert);

                $query = \OCP\DB::prepare($sql);
                $resRsrc = $query->execute($args);
            } else {
                // We have to insert or modify
                $sql = 'SELECT descr FROM *PREFIX*oclife_humanReadable WHERE tagid=? AND lang=?';
                $args = array($tagId, $tagLangToInsert);

                $query = \OCP\DB::prepare($sql);
                $resRsrc = $query->execute($args);

                $dataRow = $resRsrc->fetchRow();

                if(isset($dataRow['descr'])) {
                    // Perform an update
                    $sql = 'UPDATE *PREFIX*oclife_humanReadable SET descr=? WHERE tagid=? and lang=?';
                    $args = array($tagDescrToInsert, $tagId, $tagLangToInsert);
                    $query = \OCP\DB::prepare($sql);
                    $resRsrc = $query->execute($args);
                } else {
                    // Perform an insertion
                    $sql = 'INSERT INTO *PREFIX*oclife_humanReadable (tagid, lang, descr) VALUES (?,?,?)';
                    $args = array($tagId, $tagLangToInsert, $tagDescrToInsert);
                    $query = \OCP\DB::prepare($sql);
                    $resRsrc = $query->execute($args);
                }
            }
        }

        return true;
    }

    /**
     * Returns an array of all existing tags with given language
     * @param string $tagLang Language of the description to be returned
     */
    public function getAllTags($tagLang) {
        $result = array();

        // *PREFIX* is being replaced with the ownCloud installation prefix
        $sql = "SELECT * FROM *PREFIX*oclife_humanReadable WHERE lang=? ORDER BY tagid";
        $args = array($tagLang);

        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);
        while($row = $resRsrc->fetchRow()) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * Returns tags starting with given string
     * @param string $tagLang
     * @param string $startTag
     * @return array
     */
    public function getTag($tagLang, $startTag) {
        $result = array();

        // *PREFIX* is being replaced with the ownCloud installation prefix
        $sql = "SELECT * FROM *PREFIX*oclife_humanReadable WHERE lang=? and descr LIKE ?%";
        $args = array($tagLang, $startTag);

        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);
        while($row = $resRsrc->fetchRow()) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * Return a tree array with all the tags starting from $startTag
     * @param String $tagLang Tag language of $startTag
     * @return array
     */
    public function getTagTree($tagLang) {
        if($tagLang == '') {
            return -1;
        }

        // Get all tags with no parent
        $sql = 'SELECT id FROM *PREFIX*oclife_tags WHERE parent=-1 ORDER BY id';
        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute();

        $ids = array();
        while($row = $resRsrc->fetchRow()) {
            $ids[] = intval($row['id']);
        }

        $result = array(
            0 => array(
                'key' => '-1',
                'title' => 'Root',
                'expanded' => true,
                'class' => 'global',
                'children' => array()
                )
            );

        foreach($ids as $id) {
            $result[0]['children'][] = $this->getTagTreeFromID($id, $tagLang);
        }

        return $result;
    }

    /**
     * Get the tag tree starting from tag with given ID
     * @param integer $ID Tag ID where to start the tree
     * @param string $lang Language of the human readable field
     * @return array The tag tree result
     */
    public function getTagTreeFromID($ID, $lang = 'xx') {
        // If no ID provided - forfait
        if($ID == NULL || !is_int($ID)) {
            return -1;
        }

        // Retrieve tag data
        $sql = 'SELECT * FROM *PREFIX*oclife_humanReadable WHERE tagid=? AND lang=?';
        $args = array($ID, $lang);
        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);

        while($row = $resRsrc->fetchRow()) {
            $result['key'] = $row['tagid'];
            $result['title'] = $row['descr'];
            $result['class'] = 'global';
        }

        $result['children'] = array();

        // Fetch all childs ids if any
        $childsIDs = array();
        $sql = 'SELECT id FROM *PREFIX*oclife_tags WHERE parent=?';
        $args = array($ID);
        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);

        while($row = $resRsrc->fetchRow()) {
            $childsIDs[] = $row['id'];
        }

        // Fetch all childs data
        $childsData = array();

        foreach($childsIDs as $id) {
            $childsData[] = $this->getTagTreeFromID(intval($id), $lang);
        }

        $result['children'] = $childsData;

        return $result;
    }

    /**
     * Set the parent of a tag
     * @param String $tag Plain language (EN, IT, FR, ...) description of child tag
     * @param String $parent Plain language (EN, IT, FR, ...) description of parent tag - Empty string means: "Set parent as root"
     * @param String $lang Language ID (EN, IT, FR, ...) of both tag
     */
    public function setTagParent($tag, $parent, $lang) {
        // Verify for right parent
        if($parent !== '') {
            $sql = 'SELECT tagid FROM *PREFIX*oclife_humanReadable WHERE lang=? AND descr=?';
            $args = array($lang, $parent);
            $query = \OCP\DB::prepare($sql);
            $resRsrc = $query->execute($args);

            $parentData = array();

            while($row = $resRsrc->fetchRow()) {
                $parentData[] = $row;
            }

            if(count($parentData) != 1) {
                throw new Exception("Bad or no parent data found - $parent");
            }
        }

        // Verify for right child
        if($tag !== '') {
            $sql = 'SELECT tagid FROM *PREFIX*oclife_humanReadable WHERE lang=? AND descr=?';
            $args = array($lang, $tag);
            $query = \OCP\DB::prepare($sql);
            $resRsrc = $query->execute($args);

            $tagData = array();

            while($row = $resRsrc->fetchRow()) {
                $tagData[] = $row;
            }

            if(count($tagData) != 1) {
                throw new Exception("Bad or no child data found - $tag");
            }
        }

        $this->setTagParentByID($tagData[0]['tagid'], $parentData[0]['tagid']);
        return TRUE;
    }

    /**
     * Set tag parent by their ID
     * @param integer $tagID Tag ID to be set
     * @param integer $parentID ID of the parent
     */
    public function setTagParentByID($tagID, $parentID) {
        // Proceed to set tag's parent
        $sql = 'UPDATE *PREFIX*oclife_tags SET parent=? WHERE id=?';
        $args = array($parentID, $tagID);
        $query = \OCP\DB::prepare($sql);
        $query->execute($args);
    }

    /**
     * Get all IDs of the child of given tag
     * @param array $parentID
     * @return array All tags ID in hierarchical format
     */
    private function getAllChildIDHierarchical($parentID) {
        // If no ID provided - forfait
        if($parentID === NULL || !is_int($parentID)) {
            return -1;
        }

        $result = array();

        // Fetch all childs ids if any
        $sql = 'SELECT id FROM *PREFIX*oclife_tags WHERE parent=?';
        $args = array($parentID);
        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);

        while($row = $resRsrc->fetchRow()) {
            $result[] = $row['id'];
        }

        // Fetch all childs id
        foreach($result as $id) {
            $result[] = $this->getAllChildIDHierarchical(intval($id));
        }

        return $result;
    }

    /**
     * Get all IDs of the child of given tag
     * @param array $parentID
     * @return array All tags ID in (hierarchical?) format
     */
    public function getAllChildID($parentID) {
        // Get all IDs in hierarchical format
        $hResult = $this->getAllChildIDHierarchical($parentID);

        if(is_array($hResult)) {
            // Flatten the results
            $objTmp = (object) array('aFlat' => array());

            array_walk_recursive($hResult, create_function('&$v, $k, &$t', '$t->aFlat[] = $v;'), $objTmp);

            // Add also the original ID to the result
            $result = array($parentID);
            $childs = $objTmp->aFlat;

            foreach($childs as $child) {
                $result[] = $child;
            }
        } else {
            $result = -1;
        }

        return $result;
    }

    /**
     * Delete a tag and all it's childs
     * @param integer $tagID Tag to be deleted
     * @return array Deleted tags
     */
    public function deleteTagAndChilds($tagID) {
        // Check if $tagID is an integer
        if(!is_int($tagID)) {
            return FALSE;
        }

        // Get all id of childs
        $tagsToDelete = $this->getAllChildID($tagID);

        // Delete all tags
        $this->deleteTags($tagsToDelete);

        // Return an array of deleted tags
        return $tagsToDelete;
    }

    /**
     * Delete all tags with IDs on array
     * @param array $tagsToDelete Tags ID to delete
     */
    public function deleteTags($tagsToDelete) {
        // Check if $tagsToDelete is array
        if(!is_array($tagsToDelete)) {
            return FALSE;
        }

        // Execute deletion
        foreach ($tagsToDelete as $id) {
            // Delete from tags
            $sql = 'DELETE FROM *PREFIX*oclife_tags WHERE id=?';
            $args = array($id);
            $query = \OCP\DB::prepare($sql);
            $query->execute($args);

            // Delete from human readable
            $sql = 'DELETE FROM *PREFIX*oclife_humanreadable WHERE tagid=?';
            $args = array($id);
            $query = \OCP\DB::prepare($sql);
            $query->execute($args);
        }

        return TRUE;
    }

    /**
     * Add a tag for a file ID
     * @param integer $fileID File ID where to add the tag
     * @param integer $tagID ID of tag to be added
     * @return boolean TRUE if success, FALSE otherwise
     */
    public static function addTagForFile($fileID, $tagID) {
        // Check if tag is already present
        $result = array();
        $sql = 'SELECT id FROM *PREFIX*oclife_docTags WHERE fileid=? AND tagid=?';
        $args = array($fileID, $tagID);
        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);

        while($row = $resRsrc->fetchRow()) {
            $result[] = $row['id'];
        }

        if(count($result) != 0) {
            return FALSE;
        }

        // Proceed to add the tag
        $sql = 'INSERT INTO *PREFIX*oclife_docTags (fileid, tagid) VALUES (?,?)';
        $args = array($fileID, $tagID);
        $query = \OCP\DB::prepare($sql);
        $query->execute($args);

        return TRUE;
    }

    /**
     * Add a tag for a bunch of file IDs
     * @param array $fileID File IDs where to add the tag
     * @param integer $tagID ID of tag to be added
     * @return boolean TRUE if success, FALSE otherwise
     */
    public static function addTagForFiles($fileIDs, $tagID) {
        // Not an array - Exit
        if(!is_array($fileIDs)) {
            return FALSE;
        }

        // Do add
        foreach($fileIDs as $fileID) {
            hTags::addTagForFile($fileID, $tagID);
        }

        return TRUE;
    }

    /**
     * Remove a tag for a file ID
     * @param integer $fileID ID of file to remove the tag
     * @param integer $tagID Tag to be removed
     * @return boolean TRUE if successfull
     */
    public static function removeTagForFile($fileID, $tagID) {
        // Proceed to add the tag
        $sql = 'DELETE FROM *PREFIX*oclife_docTags WHERE fileid=? AND tagid=?';
        $args = array($fileID, $tagID);
        $query = \OCP\DB::prepare($sql);
        $query->execute($args);

        return TRUE;
    }

    /**
     * Remove a tag for a bunch of file ID
     * @param array $fileIDs IDs of file to remove the tag
     * @param integer $tagID Tag to be removed
     * @return boolean TRUE if successfull
     */
    public static function removeTagForFiles($fileIDs, $tagID) {
        // Not an array - Exit
        if(!is_array($fileIDs)) {
            return FALSE;
        }

        // Do remove
        foreach($fileIDs as $fileID) {
            if(!hTags::removeTagForFile($fileID, $tagID)) {
                return FALSE;
            }
        }

        return TRUE;
    }

    /**
     * Get all tags that are commons for a bunch of files
     * @param array $fileIDs IDs of the files
     * @return array Tags IDs commons to all the files
     */
    public static function getCommonTagsForFiles($fileIDs) {
        // Not an array - Exit
        if(!is_array($fileIDs)) {
            return FALSE;
        }

        $result = array();
        $firstPass = TRUE;

        foreach($fileIDs as $fileID) {
            $tagsForFile = hTags::getAllTagsForFile($fileID);

            // If first pass assign tags to array, otherwise intersect
            if($firstPass) {
                $result = $tagsForFile;
                $firstPass = FALSE;
            } else {
                $result = array_intersect($result, $tagsForFile);
            }
        }

        return $result;
    }


    /**
     * Get all tags for a file ID
     * @param type $fileID
     * @return array Description
     */
    public static function getAllTagsForFile($fileID) {
        $result = array();
        $sql = 'SELECT tagid FROM *PREFIX*oclife_docTags WHERE fileid=?';
        $args = array($fileID);
        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);

        while($row = $resRsrc->fetchRow()) {
            $result[] = $row['tagid'];
        }

        return $result;
    }

    /**
     * Used to remove all tags for a file
     * @param type $fileID
     * @return boolean
     */
    public static function removeAllTagsForFile($fileID) {
        $sql = 'DELETE FROM *PREFIX*oclife_docTags WHERE fileid=?';
        $args = array($fileID);
        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);

        return TRUE;
    }

    /**
     * Get the files with the indicated tag
     * @param integer $tagID Tag to look at
     * @return array ID of the files marked with the tag
     */
    public static function getFileWithTag($tagID) {
        $result = array();
        $sql = 'SELECT fileid FROM *PREFIX*oclife_docTags WHERE tagid=?';
        $args = array($tagID);
        $query = \OCP\DB::prepare($sql);
        $resRsrc = $query->execute($args);

        while($row = $resRsrc->fetchRow()) {
            $result[] = intval($row['fileid']);
        }

        return $result;
    }

    /**
     * Array containing all the tags to be looked for
     * @param array $tagArray Tag to look at - Tags will be OR'ed
     */
    public static function getFileWithTagArray($tagArray) {
        // If is not an array, gives up
        if(!is_array($tagArray)) {
            return -1;
        }

        $filesID = array();
        foreach($tagArray as $tag) {
            $partFilesID = hTags::getFileWithTag($tag);
            $filesID = array_merge($filesID, $partFilesID);
        }

        $uniquesID = array_unique($filesID);

        return $uniquesID;
    }
}
