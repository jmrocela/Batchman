<?php
/**
 * BatchInterface.php
 *
 * This file is part of the Batch Base package. This Class provides the interface
 * for batch files. Please refer to the Documentation for more information.
 * 
 * (c) 2012 Allproperty Media Pte. Ltd. <webmaster@allproperty.com.sg>
 */

/**
 * @author      John Rocela <johnmark@allproperty.com.sg>
 * @date        June 12, 2012
 */
interface BatchInterface {

    /**
  * a constructor method for the batch that can be overridden from the BatchBase
  */ 
    public function init();
    
    /**
  * the actual logic block for the batch file
  */ 
    public function action();
    
}