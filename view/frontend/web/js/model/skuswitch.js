
define([
    'jquery',
    'mage/utils/wrapper'
], function ($, wrapper) {
    'use strict';
    return function(targetModule){


        var reloadPrice = targetModule.prototype._reloadPrice;
        targetModule.prototype.configurableSku = $('div.product-info-main .sku .value').html();

        var formatter = new Intl.NumberFormat('en-US', {
          style: 'currency',
          currency: 'USD',
          minimumFractionDigits: 2,
        });

        var reloadPriceWrapper = wrapper.wrap(reloadPrice, function(original){
            //do extra stuff
           var simpleSku = this.configurableSku;
            var allSelected = true;

            if(this.simpleProduct){
                simpleSku = this.options.spConfig.skus[this.simpleProduct];
            }

          //  $('div.product-info-main .sku .value').html(simpleSku );
          //  $('div.price-final_price .normal-price .price-container .price-wrapper .price').html(simpleSku + '^^');


            var basePrice = 0;
            if(this.simpleProduct ){
                simpleSku = this.options.spConfig.skus[this.simpleProduct];
                //basePrice=this.options.spConfig.prices.basePrice['amount'];
                // $('div.price-final_price .normal-price .price-container .price-wrapper .price:first').html("Checking price");
                //var products = this._CalcProducts();
                var url= BASE_URL + "/altitudep21/index/getajax"
               // simpleSku = this.options.jsonConfig.skus[products.slice().shift()];
                console.time('test 1');
                $.ajax({
                  url: url,
                  type: "GET",
                  async: false,
                  data: {
                      sku: simpleSku,
                  }
                  //context: document.body
                }).done(function(msg) {
                  //$( this ).addClass( "done" );
                  //$('#tab-label-description-title').html("!!!!!");
                    basePrice=msg;//this.options.jsonConfig.prices.basePrice['amount'] ;
                });
                console.timeEnd('test 1');
            } else {
                console.log('not config');
            }

            $('div.product-info-main .sku .value:first').html(simpleSku );
            if (basePrice>0) {
                basePrice=formatter.format(basePrice);
                $('div.price-final_price .normal-price .price-container .price-wrapper .price:first').html(basePrice );
                $('.product-info-price .price-box .price-container .price-wrapper:first ').data('priceAmount',basePrice)
            } else{
                $('div.price-final_price .normal-price .price-container .price-wrapper .price:first').html("Select option to see price");
            }

            //$(".price").text().replace("$0.00", "Select option to see price");
            $('.price').contents().filter(function() {
                return this.nodeType == 3
            }).each(function(){
                this.textContent = this.textContent.replace('$0.00','Select option to see price');
            });
            //return original value
            //return original();
        });

        targetModule.prototype._reloadPrice = reloadPriceWrapper;
        return targetModule;
    };
});
