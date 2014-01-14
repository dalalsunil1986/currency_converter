/*
 * Author: Joel Capillo
 * 
 *
 * Project Name: Currency Converter
 * Version: 1.0
 *
 */

ConverterApp.config(['$httpProvider', function($httpProvider) {
    var interceptor = ['$q', 'LoadingIndicatorHandler', function($q, LoadingIndicatorHandler) {
        return function(promise) {
            LoadingIndicatorHandler.enable();
            
            return promise.then(
                function( response ) {
                    LoadingIndicatorHandler.disable();
                    
                    return response;
                },
                function( response ) {
                    LoadingIndicatorHandler.disable();
                    
                    // Reject the reponse so that angular isn't waiting for a response.
                    return $q.reject( response );
                }
            );
        };
    }];
    
    $httpProvider.responseInterceptors.push(interceptor);
    
}]);

ConverterApp.directive('chosen',function(){
   var linker = function(scope,element,attrs){
       var list = attrs['chosen'];
       element.trigger('chosen:updated');
       scope.$watch(list,function(){
	   element.trigger('chosen:updated');
       });
       
       scope.$watch(attrs['ngModel'], function() {

          element.trigger('chosen:updated');

       });

       element.chosen();
   };
   
   return{
     restrict:'A',
     link: linker
   }
});
 

ConverterApp.factory('LoadingIndicatorHandler', function()
{
   
    var $element = $('#loading-indicator');
    
    return {
        // Counters to keep track of how many requests are sent and to know
        // when to hide the loading element.
        enable_count: 0,
        disable_count: 0,
        
        /**
         * Fade the blocker in to block the screen.
         *
         * @return {void}
         */
        enable: function() {
            this.enable_count++;
            
            if ( $element.length ) $element.show();
        },
        
        /**
         * Fade the blocker out to unblock the screen.
         *
         * @return {void}
         */
        disable: function() {
            this.disable_count++;
            
            if ( this.enable_count == this.disable_count ) {
                if ( $element.length ) $element.hide();
            }
        }
    }
});

ConverterApp.factory('pageFactory', function($http) {
    return {        
        baseRequest : function(url) {          
            return $http.get(url).then(function(result) {
               return result.data;
            });
        }
        
    };
});

/**
 * Used to restrict user for entering non-numeric inputs
 * Used as client-max 
 * @param {type} clientMax the name of directive
 * 
 * 
 */
ConverterApp.directive('amountConvert', function() {
  return {
    require: 'ngModel',
    link: function (scope, element, attr, ngModelCtrl) {
      function fromUser(text) {
        if(!text)
            return false;
        var transformedInput = text.replace(/[^0-9.]/g, '');
       
        if(transformedInput !== text) {
            ngModelCtrl.$setViewValue(transformedInput);
            ngModelCtrl.$render();
        }
        return transformedInput;  // or return Number(transformedInput)
      }
      ngModelCtrl.$parsers.push(fromUser);
    }
  }; 
});



function page_controller($scope, $http){
    
    var currencies_url = '../web/api/load'; //api for retrieving currencies
    
    $scope.amount = null;
    $scope.currency_code_input = null; //currency code to convert
    $scope.currency_code_output = null; //currency code to output
    $scope.currencies = [];
    $scope.resultMessage = [];
    
    
    //called on page load
    $scope.init = function() {
<<<<<<< HEAD
=======
        console.log('Initializing.......');
>>>>>>> 8e8e5d92fad6924dc5f9ced68bfdd4639ba20b8d
        retrieveCurrencies();	
    };
    
    //gets the url for the flag icon
    $scope.getFlag = function(symbol){
        if(undefined !== symbol){               
            var url = 'http://s.xe.com/v2/themes/xe/images/flags/big/'+symbol.toLowerCase()+'.png';
            return url;
        }
    };
    
    //submits and create a request
    $scope.convert = function(){
       console.log('Submitting.....'); 
    };
    
    
    
    /**
     * This do an ajax call and fill-up the dropdown list with currencies.
     * 
     * @returns {undefined}
     */
    var retrieveCurrencies = function(){        
         $http.get(currencies_url).success(function(data) {            
             if(!data.error){                
                   $scope.currencies = data.data;		   
            }
            else
               //show the result container
               handleResponse('error',data.error.message);          
            
           
        }).error(function() {            
           handleResponse('error','Something\'s wrong happened.');
        });         
    };
    
    
    
    
    var handleResponse = function(type,message){
         var response = new Array();
         response.type = type;
         var response_body = {responseMsg:message};
         response.push(response_body);
         $scope.resultMessage = response;
         return false;
     };
     
    
}
 