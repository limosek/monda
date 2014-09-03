<?php

namespace App\Presenters;

use \Exception,Nette,
	App\Model,
        Nette\Utils\DateTime as DateTime;


class HtmlMapPresenter extends MapPresenter
{   
      function renderTl() {
          $this->template->title="Monda Timeline";
          parent::renderTl();
      }
}