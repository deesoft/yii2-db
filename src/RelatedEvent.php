<?php

namespace dee\db;

use yii\base\Event;
/**
 * Description of RelatedEvent
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class RelatedEvent extends Event
{
    /**
     *
     * @var string
     */
    public $relationName;
    /**
     *
     * @var integer
     */
    public $index;
    /**
     *
     * @var \yii\db\ActiveRecord
     */
    public $item;
    /**
     *
     * @var boolean
     */
    public $isValid = true;
}
