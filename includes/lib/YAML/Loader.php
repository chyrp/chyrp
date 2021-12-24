<?php
/**
 * This package is heavily inspired by the Spyc PHP YAML implementation
 * (http://spyc.sourceforge.net/), and portions are copyright 2005-2006 Chris
 * Wanstrath.
 *
 * @author   Chris Wanstrath <chris@ozmm.org>
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @author   Mike Naberezny <mike@maintainable.com>
 * @license  http://www.horde.org/licenses/bsd BSD
 * @category Horde
 * @package  Yaml
 */

/**
 * Parse YAML strings into PHP data structures
 *
 * @author   Chris Wanstrath <chris@ozmm.org>
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @author   Mike Naberezny <mike@maintainable.com>
 * @license  http://www.horde.org/licenses/bsd BSD
 * @category Horde
 * @package  Yaml
 */
class Horde_Yaml_Loader
{
    /**
     * List of nodes with references
     * @var array
     */
    protected $_haveRefs = array();

    /**
     * All nodes
     * @var array
     */
    protected $_allNodes = array();

    /**
     * Array of node parents
     * @var array
     */
    protected $_allParent = array();

    /**
     * References
     * @var array
     */
    protected $_ref = array();

    /**
     * Last indent level
     * @var integer
     */
    protected $_lastIndent = 0;

    /**
     * Nodes by indention level.
     * @var array
     */
    protected $_indentSort = array();

    /**
     * Last node id
     * @var integer
     */
    protected $_lastNode = null;

    /**
     * Is the parser inside a block?
     *
     * Contains the block character (| or >) if inside a block.
     *
     * @var boolean|string
     */
    protected $_inBlock = false;

    /**
     * The chomp mode (strip: '-', keep: '+', clip: '')
     * @var string
     */
    protected $_chomp = '';

    /**
     * @var string
     */
    protected $_lineEnd = '';

    /**
     * @var boolean
     */
    protected $_isInline = false;

    /**
     * Next node id to use
     * @var integer
     */
    protected $_nodeId = 1;

    /**
     * Last line number parsed.
     * @var integer
     */
    protected $_lineNumber = 0;

    /**
     * Create a new YAML parser.
     */
    public function __construct()
    {
        $base = new Horde_Yaml_Node($this->_nodeId);
        $base->indent = 0;
        $this->_lastNode = $base->id;
    }

    /**
     * Returns the PHP built from all YAML parsed so far.
     *
     * @return array  PHP version of parsed YAML.
     * @throws Horde_Yaml_Exception
     * @throws ReflectionException
     */
    public function toArray()
    {
        // Chomp the final line break(s) if necessary;
        if ($this->_inBlock) {
            $this->_chomp();
        }

        // Here we travel through node-space and pick out references
        // (& and *).
        $this->_linkReferences();

        // Build the PHP array out of node-space.
        return $this->_buildArray();
    }

    /**
     * Parses a line of a YAML file.
     *
     * @param string $line The line of YAML to parse.
     *
     * @throws Horde_Yaml_Exception
     * @throws ReflectionException
     */
    public function parse($line)
    {
        // Keep track of how many lines we've parsed for friendlier
        // error messages.
        ++$this->_lineNumber;

        $trimmed = trim($line);

        // If the line starts with a tab (instead of a space), throw a fit.
        if (preg_match('/^ *(\t) *[^\t ]/', $line)) {
            $msg = "Line {$this->_lineNumber} indent contains a tab.  "
                 . 'YAML only allows spaces for indentation.';
            throw new Horde_Yaml_Exception($msg);
        }

        if (!$this->_inBlock && empty($trimmed)) {
            return;
        }
        if ($this->_inBlock && empty($trimmed)) {
            $last =& $this->_allNodes[$this->_lastNode];
            $key = key($last->data);
            if ($this->_chomp != '+') {
                $last->data[$key] = rtrim($last->data[$key]);
            }
            if ($this->_chomp != '-') {
                $last->data[$key] .= "\n";
            }
            return;
        }
        if (!$this->_inBlock &&
            (substr($trimmed, 0, 1) == '#' ||
             substr($trimmed, 0, 3) == '---')) {
            return;
        }

        // Create a new node and get its indent
        $node = new Horde_Yaml_Node($this->_nodeId++);
        $node->indent = $this->_getIndent($line);

        // Check where the node lies in the hierarchy
        if ($this->_lastIndent == $node->indent) {
            // If we're in a block, add the text to the parent's data
            if ($this->_inBlock) {
                $parent =& $this->_allNodes[$this->_lastNode];
                $parent->data[key($parent->data)] .= preg_replace(
                        '/^ {' . $this->_lastIndent . '}/',
                        '',
                        $line
                    ) . $this->_lineEnd;
            } else {
                // The current node's parent is the same as the previous
                // node's
                if (isset($this->_allNodes[$this->_lastNode])) {
                    $node->parent = $this->_allNodes[$this->_lastNode]->parent;
                }
            }
        } elseif ($this->_lastIndent < $node->indent) {
            if ($this->_inBlock) {
                $parent =& $this->_allNodes[$this->_lastNode];
                $parent->data[key($parent->data)] .= preg_replace(
                        '/^ {' . $this->_lastIndent . '}/',
                        '',
                        $line
                    ) . $this->_lineEnd;
            } else {
                // The current node's parent is the previous node
                $node->parent = $this->_lastNode;

                // If the value of the last node's data was > or | we need
                // to start blocking i.e. taking in all lines as a text
                // value until we drop our indent.
                $parent =& $this->_allNodes[$node->parent];
                $parent->children = true;
                if (is_array($parent->data)) {
                    $key = key($parent->data);
                    if (isset($parent->data[$key])) {
                        $chk = $parent->data[$key];
                        if (!is_array($chk) &&
                            preg_match('/^(>|\|)([-+\d]*)/', $chk, $match)) {
                            if ($match[1] == '>') {
                                $this->_lineEnd = ' ';
                            } else {
                                $this->_lineEnd = "\n";
                            }
                            $this->_chomp = '';
                            if ($match[2]) {
                                if (strpos($match[2], '-') !== false) {
                                    $this->_chomp = '-';
                                } elseif (strpos($match[2], '+') !== false) {
                                    $this->_chomp = '+';
                                }
                                $match[2] = str_replace(
                                    array('-', '+'), '', $match[2]
                                );
                            }
                            if ($match[2]) {
                                $this->_lastIndent = $match[2];
                            } else {
                                $this->_lastIndent = $node->indent;
                            }
                            $this->_inBlock = $match[1];
                            $parent->data[$key] = str_replace(
                                $match[0], '', $parent->data[$key]
                            );
                            $parent->data[$key] .= preg_replace(
                                '/^ {' . $this->_lastIndent . '}/',
                                '',
                                $line
                            ) . $this->_lineEnd;
                            $parent->children = false;
                        }
                    }
                }
            }
        } elseif ($this->_lastIndent > $node->indent) {
            // Any block we had going is dead now
            if ($this->_inBlock) {
                $this->_inBlock = false;
                $this->_chomp();
            }

            // We don't know the parent of the node so we have to
            // find it
            foreach (array_reverse(array_keys($this->_indentSort[$node->indent])) as $key) {
                $n = $this->_indentSort[$node->indent][$key];
                if ($n->indent == $node->indent) {
                    $node->parent = $n->parent;
                    break;
                }
            }
        }

        if (!$this->_inBlock) {
            // Set these properties with information from our
            // current node
            $this->_lastIndent = $node->indent;

            // Set the last node
            $this->_lastNode = $node->id;

            // Parse the YAML line and return its data
            $node->data = $this->_parseLine($line);

            // Add the node to the master list
            $this->_allNodes[$node->id] = $node;

            // Add a reference to the parent list
            $this->_allParent[intval($node->parent)][] = $node->id;

            // Add a reference to the node in an indent array
            $this->_indentSort[$node->indent][] =& $this->_allNodes[$node->id];

            // Add a reference to the node in a References array
            // if this node has a YAML reference in it.
            $is_array = is_array($node->data);
            $key = key($node->data);
            $isset = isset($node->data[$key]);
            if ($isset) {
                $nodeval = $node->data[$key];
                if ($is_array) {
                    if (is_string($nodeval) && strlen($nodeval) &&
                        ($nodeval[0] == '&' || $nodeval[0] == '*') &&
                        isset($nodeval[1]) && $nodeval[1] != ' ') {
                        $this->_haveRefs[] =& $this->_allNodes[$node->id];
                    } elseif (is_array($nodeval)) {
                        // Incomplete reference making code. Needs to be
                        // cleaned up.
                        foreach ($node->data[$key] as $d) {
                            if (is_string($d) && strlen($d) &&
                                ($d[0] == '&' || $d[0] == '*') &&
                                isset ($d[1]) && $d[1] != ' ') {
                                $this->_haveRefs[] =& $this->_allNodes[$node->id];
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Chomps trailing white space if necessary.
     */
    protected function _chomp()
    {
        $last =& $this->_allNodes[$this->_lastNode];
        $key = key($last->data);
        if (!$this->_chomp) {
            $last->data[$key] = rtrim($last->data[$key]) . "\n";
        }
        if ($this->_chomp == '-') {
            $last->data[$key] = rtrim($last->data[$key]);
        }
    }

    /**
     * Finds and returns the indentation of a YAML line.
     *
     * @param string $line  A line from the YAML file.
     *
     * @return integer  Indentation level.
     */
    protected function _getIndent($line)
    {
        if (preg_match('/^\s+/', $line, $match)) {
            return strlen($match[0]);
        }
        return 0;
    }

    /**
     * Parses YAML code and returns an array for a node.
     *
     * @param string $line A line from the YAML file.
     *
     * @return array
     * @throws Horde_Yaml_Exception
     * @throws ReflectionException
     */
    protected function _parseLine($line)
    {
        $array = array();

        $line = trim($line);
        if (preg_match('/^- (.*):$/', $line)) {
            // It's a mapped sequence
            $key = trim(substr(substr($line, 1), 0, -1));
            $array[$key] = '';
        } elseif (substr($line, 0, 2) == '- ') {
            // It's a list item but not a new stream
            if (strlen($line) > 1) {
                // Set the type of the value. Int, string, etc
                $array[] = $this->_toType(trim(substr($line, 1)));
            } else {
                $array[] = array();
            }
        } elseif (preg_match('/^(.+):/', $line, $key)) {
            // It's a key/value pair most likely
            // If the key is in double quotes pull it out
            if (preg_match('/^(["\'](.*)["\'](\s)*:)/', $line, $matches)) {
                $value = trim(str_replace($matches[1], '', $line));
                $key = $matches[2];
            } else {
                // Do some guesswork as to the key and the value
                $explode = explode(':', $line);
                $key = trim(array_shift($explode));
                $value = trim(implode(':', $explode));
            }

            // Set the type of the value. Int, string, etc
            $value = $this->_toType($value);
            if (empty($key)) {
                $array[] = $value;
            } else {
                $array[$key] = $value;
            }
        }

        return $array;
    }

    /**
     * Finds the type of the passed value, returns the value as the new type.
     *
     * @param string $value
     *
     * @return mixed
     * @throws Horde_Yaml_Exception
     * @throws ReflectionException
     */
    protected function _toType($value)
    {
        // Check for PHP specials
        self::_unserialize($value);
        if (!is_scalar($value)) {
            return $value;
        }

        // Used in a lot of cases.
        $lower_value = strtolower($value);

        if (preg_match('/^("(.*)"|\'(.*)\')/', $value, $matches)) {
            $value = (string)str_replace(array('\'\'', '\\\''), "'", end($matches));
            $value = str_replace('\\"', '"', $value);
        } elseif (preg_match('/^\\[(\s*)\\]$/', $value)) {
            // empty inline mapping
            $value = array();
        } elseif (preg_match('/^\\[(.+)\\]$/', $value, $matches)) {
            // Inline Sequence

            // Take out strings sequences and mappings
            $explode = $this->_inlineEscape($matches[1]);

            // Propogate value array
            $value  = array();
            foreach ($explode as $v) {
                $value[] = $this->_toType($v);
            }
        } elseif (preg_match('/^\\{(\s*)\\}$/', $value)) {
            // empty inline mapping
            $value = array();
        } elseif (strpos($value, ': ') !== false &&
                  !preg_match('/^{(.+)/', $value)) {
            // inline mapping
            $array = explode(': ', $value);
            $key = trim($array[0]);
            array_shift($array);
            $value = trim(implode(': ', $array));
            $value = $this->_toType($value);
            $value = array($key => $value);
        } elseif (preg_match("/{(.+)}$/", $value, $matches)) {
            // Inline Mapping

            // Take out strings sequences and mappings
            $explode = $this->_inlineEscape($matches[1]);

            // Propogate value array
            $array = array();
            foreach ($explode as $v) {
                $array = $array + $this->_toType($v);
            }
            $value = $array;
        } elseif ($lower_value == 'null' || $value == '' || $value == '~') {
            $value = null;
        } elseif ($lower_value == '.nan') {
            $value = NAN;
        } elseif ($lower_value == '.inf') {
            $value = INF;
        } elseif ($lower_value == '-.inf') {
            $value = -INF;
        } elseif (ctype_digit($value)) {
            $value = (int)$value;
        } elseif (in_array($lower_value,
                           array('true', 'on', '+', 'yes', 'y'))) {
            $value = true;
        } elseif (in_array($lower_value,
                           array('false', 'off', '-', 'no', 'n'))) {
            $value = false;
        } elseif (is_numeric($value)) {
            $value = (float)$value;
        } else {
            // Just a normal string, right?
            if (($pos = strpos($value, '#')) !== false) {
                $value = substr($value, 0, $pos);
            }
            $value = trim($value);
        }

        return $value;
    }

    /**
     * Handles PHP serialized data.
     *
     * @param string &$data Data to check for serialized PHP types.
     *
     * @throws Horde_Yaml_Exception
     * @throws ReflectionException
     */
    protected function _unserialize(&$data)
    {
        if (substr($data, 0, 5) != '!php/') {
            return;
        }

        $first_space = strpos($data, ' ');
        $type = substr($data, 5, $first_space - 5);
        $class = null;
        if (strpos($type, '::') !== false) {
            list($type, $class) = explode('::', $type);

            if (!in_array($class, Horde_Yaml::$allowedClasses)) {
                throw new Horde_Yaml_Exception("$class is not in the list of allowed classes");
            }
        }

        switch ($type) {
        case 'object':
            if (!class_exists($class)) {
                throw new Horde_Yaml_Exception("$class is not defined");
            }

            $reflector = new ReflectionClass($class);
            if (!$reflector->implementsInterface('Serializable')) {
                throw new Horde_Yaml_Exception("$class does not implement Serializable");
            }

            $class_data = trim(substr($data, $first_space + 1));
            $serialized = 'C:' . strlen($class) . ':"' . $class . '":' . strlen($class_data) . ':{' . $class_data . '}';
            $data = unserialize($serialized);
            break;

        case 'array':
        case 'hash':
            $array_data = substr($data, $first_space + 1);
            $array_data = Horde_Yaml::load('a: ' . $array_data);

            if (is_null($class)) {
                $data = $array_data['a'];
                break;
            }
            if (!class_exists($class)) {
                throw new Horde_Yaml_Exception("$class is not defined");
            }

            $array = new $class;
            if (!$array instanceof ArrayAccess) {
                throw new Horde_Yaml_Exception("$class does not implement ArrayAccess");
            }

            foreach ($array_data['a'] as $key => $val) {
                $array[$key] = $val;
            }

            $data = $array;
            break;
        }
    }

    /**
     * Used in inlines to check for more inlines or quoted strings
     *
     * @todo  There should be a cleaner way to do this.  While
     *        pure sequences seem to be nesting just fine,
     *        pure mappings and mappings with sequences inside
     *        can't go very deep.  This needs to be fixed.
     *
     * @param string $inline  Inline data.
     *
     * @return array
     */
    protected function _inlineEscape($inline)
    {
        $saved_strings = array();

        // Check for strings
        $regex = '/(?:(")|(?:\'))((?(1)[^"]+|[^\']+))(?(1)"|\')/';
        if (preg_match_all($regex, $inline, $strings)) {
            $saved_strings = $strings[0];
            $inline = preg_replace($regex, 'YAMLString', $inline);
        }

        // Check for sequences
        if (preg_match_all('/\[(.+)\]/U', $inline, $seqs)) {
            $inline = preg_replace('/\[(.+)\]/U', 'YAMLSeq', $inline);
            $seqs = $seqs[0];
        }

        // Check for mappings
        if (preg_match_all('/{(.+)}/U', $inline, $maps)) {
            $inline = preg_replace('/{(.+)}/U', 'YAMLMap', $inline);
            $maps = $maps[0];
        }

        $explode = explode(', ', $inline);

        // Re-add the sequences
        if (!empty($seqs)) {
            $i = 0;
            foreach ($explode as $key => $value) {
                if (strpos($value, 'YAMLSeq') !== false) {
                    $explode[$key] = str_replace('YAMLSeq', $seqs[$i], $value);
                    ++$i;
                }
            }
        }

        // Re-add the mappings
        if (!empty($maps)) {
            $i = 0;
            foreach ($explode as $key => $value) {
                if (strpos($value, 'YAMLMap') !== false) {
                    $explode[$key] = str_replace('YAMLMap', $maps[$i], $value);
                    ++$i;
                }
            }
        }

        // Re-add the strings
        if (!empty($saved_strings)) {
            $i = 0;
            foreach ($explode as $key => $value) {
                while (strpos($value, 'YAMLString') !== false) {
                    $explode[$key] = preg_replace('/YAMLString/', $saved_strings[$i], $value, 1);
                    ++$i;
                    $value = $explode[$key];
                }
            }
        }

        return $explode;
    }

    /**
     * Builds the PHP array from all the YAML nodes we've gathered.
     *
     * @return array
     * @throws Horde_Yaml_Exception
     * @throws ReflectionException
     */
    protected function _buildArray()
    {
        $trunk = array();
        if (!isset($this->_indentSort[0])) {
            return $trunk;
        }

        foreach ($this->_indentSort[0] as $n) {
            if (empty($n->parent)) {
                $this->_nodeArrayizeData($n);

                // Check for references and copy the needed data to complete
                // them.
                $this->_makeReferences($n);

                // Merge our data with the big array we're building
                $trunk = $this->_array_kmerge($trunk, $n->data);
            }
        }

        return $trunk;
    }

    /**
     * Traverses node-space and sets references (& and *) accordingly.
     */
    protected function _linkReferences()
    {
        if (!is_array($this->_haveRefs)) {
            return;
        }

        foreach ($this->_haveRefs as $node) {
            if (!empty($node->data)) {
                $key = key($node->data);
                // If it's an array, don't check.
                if (is_array($node->data[$key])) {
                    foreach ($node->data[$key] as $k => $v) {
                        $this->_linkRef($node, $key, $k, $v);
                    }
                } else {
                    $this->_linkRef($node, $key);
                }
            }
        }
    }

    /**
     * Helper for _linkReferences().
     *
     * @param Horde_Yaml_Node $n Node.
     * @param string $key        Key.
     * @param string $k          Key.
     * @param mixed $v           Value.
     */
    protected function _linkRef($n, $key, $k = null, $v = null)
    {
        if (empty($k) && empty($v)) {
            // Look for &refs
            if (preg_match('/^&([^ ]+)/', $n->data[$key], $matches)) {
                // Flag the node so we know it's a reference
                $this->_allNodes[$n->id]->ref = substr($matches[0], 1);
                $this->_allNodes[$n->id]->data[$key] =
                    substr($n->data[$key], strlen($matches[0]) + 1);
                // Look for *refs
            } elseif (preg_match('/^\*([^ ]+)/', $n->data[$key], $matches)) {
                $ref = substr($matches[0], 1);
                // Flag the node as having a reference
                $this->_allNodes[$n->id]->refKey = $ref;
            }
        } elseif (!empty($k) && !empty($v)) {
            if (preg_match('/^&([^ ]+)/', $v, $matches)) {
                // Flag the node so we know it's a reference
                $this->_allNodes[$n->id]->ref = substr($matches[0], 1);
                $this->_allNodes[$n->id]->data[$key][$k] =
                    substr($v, strlen($matches[0]) + 1);
                // Look for *refs
            } elseif (preg_match('/^\*([^ ]+)/', $v, $matches)) {
                $ref = substr($matches[0], 1);
                // Flag the node as having a reference
                $this->_allNodes[$n->id]->refKey = $ref;
            }
        }
    }

    /**
     * Finds the children of a node and aids in the building of the PHP array
     *
     * @param  int $nid The id of the node whose children we're gathering
     *
     * @return array
     * @throws Horde_Yaml_Exception
     * @throws ReflectionException
     */
    protected function _gatherChildren($nid)
    {
        $return = array();
        $node =& $this->_allNodes[$nid];
        if (is_array ($this->_allParent[$node->id])) {
            foreach ($this->_allParent[$node->id] as $nodeZ) {
                $z =& $this->_allNodes[$nodeZ];
                // We found a child
                $this->_nodeArrayizeData($z);

                // Check for references
                $this->_makeReferences($z);

                // Merge with the big array we're returning, the big
                // array being all the data of the children of our
                // parent node
                $return = $this->_array_kmerge($return, $z->data);
            }
        }
        return $return;
    }

    /**
     * Turns a node's data and its children's data into a PHP array
     *
     * @param Horde_Yaml_Node $node The node which you want to arrayize.
     *
     * @throws Horde_Yaml_Exception
     * @throws ReflectionException
     */
    protected function _nodeArrayizeData(&$node)
    {
        if ($node->children == true) {
            if (is_array($node->data)) {
                // This node has children, so we need to find them
                $children = $this->_gatherChildren($node->id);

                // We've gathered all our children's data and are ready to use
                // it
                $key = key($node->data);
                $key = empty($key) ? 0 : $key;
                // If it's an array, add to it of course
                if (isset($node->data[$key])) {
                    if (is_array($node->data[$key])) {
                        $node->data[$key] =
                            $this->_array_kmerge($node->data[$key], $children);
                    } else {
                        $node->data[$key] = $children;
                    }
                } else {
                    $node->data[$key] = $children;
                }
            } else {
                // Same as above, find the children of this node
                $children = $this->_gatherChildren($node->id);
                $node->data = array();
                $node->data[] = $children;
            }
        } else {
            // The node is a single string. See if we need to unserialize it.
            if (is_array($node->data)) {
                $key = key($node->data);
                $key = empty($key) ? 0 : $key;

                if (!isset($node->data[$key]) ||
                    is_array($node->data[$key]) ||
                    is_object($node->data[$key])) {
                    return;
                }

                self::_unserialize($node->data[$key]);
            } elseif (is_string($node->data)) {
                self::_unserialize($node->data);
            }
        }
    }

    /**
     * Traverses node-space and copies references to / from this object.
     *
     * @param Horde_Yaml_Node $z  A node whose references we wish to make real.
     */
    protected function _makeReferences(&$z)
    {
        if (isset($z->ref)) {
            // It is a reference
            $key = key($z->data);
            // Copy the data to this object for easy retrieval later
            $this->_ref[$z->ref] =& $z->data[$key];
        } elseif (isset($z->refKey)) {
            // It has a reference
            if (isset($this->_ref[$z->refKey])) {
                $key = key($z->data);
                // Copy the data from this object to make the node a real
                // reference
                $z->data[$key] =& $this->_ref[$z->refKey];
            }
        }
    }

    /**
     * Merges two arrays, maintaining numeric keys.
     *
     * If two numeric keys clash, the second one will be appended to the
     * resulting array. If string keys clash, the last one wins.
     *
     * @param array $arr1
     * @param array $arr2
     *
     * @return array
     */
    protected function _array_kmerge($arr1, $arr2)
    {
        foreach ($arr2 as $key => $val) {
            if (isset($arr1[$key]) && is_int($key)) {
                $arr1[] = $val;
            } else {
                $arr1[$key] = $val;
            }
        }

        return $arr1;
    }
}
