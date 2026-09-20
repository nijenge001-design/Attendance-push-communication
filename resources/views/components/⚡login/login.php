<?php

use App\Livewire\Forms\LoginForm;
use Livewire\Component;

new class extends Component
{
    public LoginForm $form;

    public function submit():void{
        $this->form->submit();
    }
};
