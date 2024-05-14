<?php
namespace App\Services\FpTree;

class FPNode
{
    public $value; //id sản phẩm
    public $count; //số lần xuất hiện
    public $parent;
    public $link;
    public $children = [];

    public function __construct($value, int $count, ?FPNode $parent)
    {
        $this->value = $value;
        $this->count = $count;
        $this->parent = $parent;
        $this->link = null;
        $this->children = [];
    }
    public function hasChild($value): bool
    {
        foreach ($this->children as $node) {
            if ($node->value == $value) {
                return true;
            }
        }
        return false;
    }
    public function getChild($value): ?FPNode
    {
        foreach ($this->children as $node) {
            if ($node->value == $value) {
                return $node;
            }
        }
        return null;
    }
    public function addChild($value): FPNode
    {
        $child = new FPNode($value, 1, $this);
        $this->children[] = $child;
        return $child;
    }
    
}