<?php
  


?>
  
    <div class="left-column">   
            <table class="selectable-table">
                <thead>
                    <tr>
                        <!--th>Payments</th-->
                        <th>פריט</th>
                        <th>סיכום</th>                   
                    </tr>
                </thead>
                <tbody>   
                    <tr>
                    <td style="width: 80%;">
                    <div class="product-col">
                        <div>
                            <?php $image = get_post_meta($post->ID, 'image', true) ?? ''; ?>
                            <?php if($image == 1){ ?>
                                <img src="<?php echo $product->images[0] ?? ''; ?>"> 
                            <?php } ?>           
                            </div>
                            <div>
                                <div><h2><?php echo $product->name ?></h2></div>
                                <div><p><?php echo $product->description ?></p></div>
                                </div>
                            </div>
                    </td>
                    <td>
                    <?php     
                        $image = get_post_meta($post->ID, 'image', true) ?? '';
                        $payments="<b>סה\"כ לתשלום</b>"; // .($price->unit_amount / 100)." X ".$max_payments_limit;
                        $total_price   = $price->unit_amount / 100;
                    ?>    
                        <div class="price-col"> 
                        <?php echo $payments ?><b>₪<?php echo $total_price; ?></b></td>
                        </div>
                        </td>        
                    </tr>

                    
                </tbody>
            </table>
        </div>
        <?php