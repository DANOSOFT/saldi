<!DOCTYPE html> 
<html lang="en"> 
  
<head> 
    <meta charset="UTF-8"> 
    <meta name="viewport" content= 
    "width=device-width, initial-scale=1.0"> 
  
    <title>JavaScript | fetch() Method</title> 
</head> 
  
<body> 
    <script> 
  
        // API for get requests 
        let fetchRes = fetch("http://192.168.76.225/kundedisplay.php?ryd=ryd"); 
          // fetchRes is the promise to resolve 
        // it by using.then() method 
        fetchRes.then(res => 
            res.json()).then(d => { 
                console.log(d) 
            }) 
    </script> 
</body> 

</html>
