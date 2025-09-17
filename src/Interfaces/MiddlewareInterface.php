<?php
namespace App\Interfaces;

interface MiddlewareInterface
{
    /**     
     * @return mixed|null
     */
    public function process();
}