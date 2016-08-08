<?php

namespace dee\db;

use yii\db\ActiveRecord;
use yii\db\ActiveQuery;

/**
 * Description of RelationTrait
 *
 * 
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
trait RelationTrait
{
    /**
     * @var ActiveQuery[]
     */
    private $_relations = [];

    /**
     * @var ActiveRecord[]
     */
    private $_oldRelations = [];

    /**
     * @var array
     */
    private $_originalRelations = [];

    /**
     * @var boolean[]
     */
    private $_processRelations = [];

    /**
     * @var string[]
     */
    private $_relatedScenario = [];

    public function setRelatedScenario($name, $scenario)
    {
        if (!isset($this->_relatedScenario[$name]) || $this->_relatedScenario[$name] !== $scenario) {
            if (array_key_exists($name, $this->_relations)) {
                $relation = $this->_relations[$name];
            } else {
                $relation = $this->_relations[$name] = $this->getRelation($name, false);
            }

            if ($relation === null) {
                return false;
            }

            $this->_relatedScenario[$name] = $scenario;
            $children = $this->$name;
            if ($relation->multiple) {
                foreach ($children as $item) {
                    $item->setScenario($scenario);
                }
            } elseif ($children) {
                $children->setScenario($scenario);
            }
            $this->populateRelation($name, $children);
        }
    }

    /**
     * Populate relation
     * @param string $name
     * @param array||ActiveRecord||ActiveRecord[] $values
     * @return boolean
     */
    public function loadRelated($name, $values)
    {
        if (array_key_exists($name, $this->_relations)) {
            $relation = $this->_relations[$name];
        } else {
            $relation = $this->_relations[$name] = $this->getRelation($name, false);
        }

        if ($relation === null) {
            return false;
        }
        $class = $relation->modelClass;
        $multiple = $relation->multiple;
        $link = $relation->link;
        if (array_key_exists($name, $this->_originalRelations)) {
            $children = $this->_originalRelations[$name];
        } else {
            $this->_originalRelations[$name] = $children = $this->$name;
        }

        if ($multiple) {
            $newChildren = [];
            foreach ($values as $index => $item) {
                if (isset($children[$index])) {
                    if ($item instanceof $class) {
                        if ($item->isNewRecord || $item->oldPrimaryKey == $children[$index]->oldPrimaryKey) {
                            $item->oldAttributes = $children[$index]->oldAttributes;
                            unset($children[$index]);
                        }
                        $newChildren[$index] = $item;
                        if (isset($this->_relatedScenario[$name])) {
                            $newChildren[$index]->setScenario($this->_relatedScenario[$name]);
                        }
                    } else {
                        $newChildren[$index] = $children[$index];
                        if (isset($this->_relatedScenario[$name])) {
                            $newChildren[$index]->setScenario($this->_relatedScenario[$name]);
                        }
                        $newChildren[$index]->setAttributes($item);
                        unset($children[$index]);
                    }
                } elseif ($item instanceof $class) {
                    $newChildren[$index] = $item;
                    if (isset($this->_relatedScenario[$name])) {
                        $newChildren[$index]->setScenario($this->_relatedScenario[$name]);
                    }
                } else {
                    $newChildren[$index] = new $class();
                    if (isset($this->_relatedScenario[$name])) {
                        $children[$index]->setScenario($this->_relatedScenario[$name]);
                    }
                    $newChildren[$index]->setAttributes($item);
                }
                foreach ($link as $from => $to) {
                    $newChildren[$index]->$from = $this->$to;
                }
            }
            $this->_oldRelations[$name] = $children;
        } else {
            $item = $values;
            $newChildren = null;
            if (isset($children)) {
                if ($item instanceof $class) {
                    if ($item->isNewRecord || $item->oldPrimaryKey == $children->oldPrimaryKey) {
                        $item->oldAttributes = $children->oldAttributes;
                        $children = null;
                    }
                    $newChildren = $item;
                    if (isset($this->_relatedScenario[$name])) {
                        $newChildren->setScenario($this->_relatedScenario[$name]);
                    }
                } elseif (isset($item)) {
                    $newChildren = $children;
                    if (isset($this->_relatedScenario[$name])) {
                        $newChildren->setScenario($this->_relatedScenario[$name]);
                    }
                    $newChildren->setAttributes($item);
                    $children = null;
                }
            } elseif ($item instanceof $class) {
                $newChildren = $item;
                if (isset($this->_relatedScenario[$name])) {
                    $newChildren->setScenario($this->_relatedScenario[$name]);
                }
            } elseif (isset($item)) {
                $newChildren = new $class();
                if (isset($this->_relatedScenario[$name])) {
                    $newChildren->setScenario($this->_relatedScenario[$name]);
                }
                $newChildren->setAttributes($item);
            }
            if ($newChildren) {
                foreach ($link as $from => $to) {
                    $newChildren->$from = $this->$to;
                }
            }
            $this->_oldRelations[$name] = isset($children) ? [$children] : [];
        }
        $this->populateRelation($name, $newChildren);
        $this->_processRelations[$name] = true;
        return true;
    }

    public function validateRelation($name = null)
    {
        $valid = true;
        foreach ($this->_processRelations as $n => $process) {
            if (!$process || ($name !== null && $name !== $n)) {
                continue;
            }
            $relation = $this->_relations[$n];
            $link = $relation->link;
            $children = $this->$n;
            if ($relation->multiple) {
                /* @var $item ActiveRecord */
                foreach ($children as $i => $item) {
                    foreach ($link as $from => $to) {
                        $item->$from = $this->$to;
                    }
                    if (!$item->validate()) {
                        $valid = false;
                        foreach ($item->firstErrors as $error) {
                            $this->addError($n, "$n[$i] $error");
                            break;
                        }
                    }
                }
            } else {
                if ($children) {
                    foreach ($link as $from => $to) {
                        $children->$from = $this->$to;
                    }
                    if (!$children->validate()) {
                        $valid = false;
                        foreach ($children->firstErrors as $error) {
                            $this->addError($n, "$n $error");
                            break;
                        }
                    }
                }
            }
        }
        return $valid;
    }

    public function saveRelation($runValidation = true, $name = null)
    {
        if (!$runValidation || $this->validateRelation($name)) {
            foreach ($this->_processRelations as $n => $process) {
                if (!$process || ($name !== null && $name !== $n)) {
                    continue;
                }
                foreach ($this->_oldRelations[$n] as $item) {
                    $item->delete();
                }
                unset($this->_oldRelations[$n]);
                $relation = $this->_relations[$n];
                $link = $relation->link;
                $children = $this->$n;
                if ($relation->multiple) {
                    /* @var $item ActiveRecord */
                    foreach ($children as $i => $item) {
                        foreach ($link as $from => $to) {
                            $item->$from = $this->$to;
                        }
                        $event = new RelatedEvent([
                            'relationName' => $n,
                            'index' => $i,
                            'item' => $item,
                        ]);
                        $this->trigger('beforeRelatedSave', $event);
                        if ($event->isValid) {
                            $item->save(false);
                        } elseif (!$item->isNewRecord) {
                            $item->delete();
                        }
                    }
                } else {
                    if ($children) {
                        foreach ($link as $from => $to) {
                            $children->$from = $this->$to;
                        }
                        $event = new RelatedEvent([
                            'relationName' => $n,
                            'item' => $children,
                        ]);
                        $this->trigger('beforeRelatedSave', $event);
                        if ($event->isValid) {
                            $children->save(false);
                        } elseif (!$children->isNewRecord) {
                            $children->delete();
                        }
                    }
                }
                unset($this->_processRelations[$n], $this->_originalRelations[$n]);
            }
            return true;
        }
        return false;
    }
}
