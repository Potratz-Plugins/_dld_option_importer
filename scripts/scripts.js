
jQuery( document ).ready(function() {

    jQuery("#btnReloadPage").click(function (e) {
        location.reload();
         });

         jQuery("#btnUploadFile").click(function (e) {

                var file = jQuery("#fileToUpload").val();  //Fetch the filename of the submitted file

                if (file == "") {    //Check if a file was selected
                    //Place warning text below the upload control
                    jQuery(".errorDiv").html("* Please select a valid CSV file first.");
                    e.preventDefault();
                }
                else {
                    //Check file extension
                    var ext = file.split(".").pop().toLowerCase();   //Check file extension if valid or expected
                    if (jQuery.inArray(ext, ["csv"]) == -1) {
                        jQuery(".errorDiv").html("* Please select a valid CSV file (csv).");
                        e.preventDefault(); //Prevent submission of form
                    }
                    else {
                        document.getElementById("h2PleaseWait").style.display="block";
                    }
                }
            });



            

    //    alert('jquery');
            
              });