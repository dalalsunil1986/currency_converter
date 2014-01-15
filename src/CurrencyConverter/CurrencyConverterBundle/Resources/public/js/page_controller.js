/*
 * Author: Joel Capillo
 * 
 *
 * Project Name: Currency Converter
 * Version: 1.0
 *
 */
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
   };
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
    var service_url = 'https://currencyconverter.p.mashape.com/?';
    $scope.amount = null;
    $scope.currency_code_input = null; //currency code to convert
    $scope.currency_code_output = null; //currency code to output
    $scope.currencies = [];
    $scope.resultMessage = [];
    $scope.showResult = false;
    $scope.resultAmount = null;
    
    
    //called on page load
    $scope.init = function() {
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
        
       if($scope.currency_code_input === null)
         return false;
       
       if($scope.currency_code_output === null)
          return false;
      
       if($scope.amount === null)
          return false;       
       
        var input_code = $scope.currency_code_input.currency_code;
        var output_code = $scope.currency_code_output.currency_code;
        var config = { headers:  {
              'X-Mashape-Authorization': 'cH514KK6Q30x7p7iG742raGSwU34DwIe'
            }
        };
        
        $.blockUI({ css: { 
            border: 'none', 
            padding: '15px', 
            backgroundColor: '#000', 
            '-webkit-border-radius': '10px', 
            '-moz-border-radius': '10px', 
            opacity: .5, 
            color: '#fff' 
        } });  
       
        var url = service_url+'from_amount='+$scope.amount+'&from='+input_code+'&to='+output_code;
        $http.get(url, config).success(function(data) {
           var output = data.to_amount;
           $scope.resultAmount = output.toFixed(2);
           $scope.showResult = true;
           $.unblockUI();
	}).error(function(data) {
	    handleResponse('error','An error occurred while serving the request.'); 
            $.unblockUI();
	});
        
    };
    
    $scope.reset = function(){
	$scope.showResult = false;
	$scope.amount = null;
	//$scope.currency_code_input = null; //currency code to convert
	//$scope.currency_code_output = null; //currency code to output	
	$scope.resultMessage = [];
	$scope.resultAmount = null;
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
 