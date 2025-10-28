<?php
  


?>
  
    <div class="left-column">
    <div><h2><?php echo $product->name ?></h2></div>    
    <p><?php echo $product->description ?></p>
            <table class="selectable-table">
                <thead>
                    <tr>
                        <!--th>Payments</th-->
                        <th>פריט</th>
                        <th>סיכום</th>                   
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $rows = get_post_meta($post->ID, 'rows', true) ?? '';
                    $image = get_post_meta($post->ID, 'image', true) ?? '';
                    $i = 1;
                    $v = 0;
                    if($rows){
                        
                        for ($i= $i; $i <= $max_payments_limit; $i++) {
                            ?>
                            <tr data="<?php echo $i; ?>" p-data="<?php echo $price->unit_amount; ?>">
                            <?php
                            if ($i == 1) {
                                $payments='<b>סה"כ<br>תשלום</b>'; // For single payment
                                $total_price   = $price->unit_amount * ($max_payments_limit-$v) / 100;
                            } else {
                                $payments= '<b>סה"כ<br>X' . $i . ' תשלומים</b>'; // For multiple payments
                                $total_price   = $price->unit_amount / 100;
                            }?>

                            <!--th>< ?php echo 'X'.$i?> תשלומים</th--> 
                            <td>
                                <div class="product-col">
                                    <div>
                                        <?php if($image == 1){ ?>
                                            <img src="<?php echo $product->images[0] ?? ''; ?>"> 
                                        <?php } ?>           
                                    </div>
                                    <div>
                                        <div><h2><?php echo $product->name ?></h2></div>
                                        <div></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                            <div class="price-col"> 
                            <?php echo "$payments של- " ?><b>₪<?php echo $total_price; ?></b></td>
                            </div>
                            </tr>
                        
                            <?php 
                        }
                    } else{ ?>
                        <tr data="<?php echo $i; ?>" p-data="<?php echo $price->unit_amount; ?>">
                            <?php
                            if ($i == 1) {
                                $payments="<b>סה\"כ<br>לתשלום</b>" .($price->unit_amount / 100)." X ".$max_payments_limit;
                                $total_price   = $price->unit_amount * ($max_payments_limit-$v) / 100;
                            }
                            ?>

                            <!--th>< ?php echo 'X'.$i?> תשלומים</th--> 
                            <td>
                                <div class="product-col">
                                    <div>
                                        <?php if($image == 1){ ?>
                                            <img src="<?php echo $product->images[0] ?? ''; ?>"> 
                                        <?php } ?>           
                                    </div>
                                    <div>
                                        <div><h2><?php echo $product->name ?></h2></div>
                                        <div></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                            <div class="price-col"> 
                            <?php echo $payments ?><b>₪<?php echo $total_price; ?></b></td>
                            </div>
                            </tr>
                    <?php } ?>
                    
                </tbody>
            </table>
        </div>
        <?php