<?php

namespace App\Http\Livewire;

use Livewire\Component;

class TestModal extends Component
{
    public $clicked = false;

public function testClick()
{
    $this->clicked = true;
    \Log::info('testClick method triggered');
    $this->dispatchBrowserEvent('openDeleteModal');
}
}
