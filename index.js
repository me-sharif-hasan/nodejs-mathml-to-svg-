var express=require('express');
const app = express();
app.use(require('body-parser').json());

var mj=require("mathjax-node");

mj.config({
  MathJax: {
    //To do
	SVG: { linebreaks: { automatic: true } }
  }
});
mj.start();


async function job(json,http){
  var ret={};
  var maths=json.math;
  console.log("Total "+maths.length+" equation given");
  ret.html=[];
  ret.error=[];
  var i=0;
  maths.forEach(elm=>{
    json.math=elm;
    var pr= mj.typeset(json);
    ret.html.push(pr);
  });
  var mpe={'svg':'error'};
  Promise.all(ret.html.map(p => p.catch(error => mpe))).then(vals=>{
  	http.json(vals);
  });
}



app.post("/convert",async (req,response)=>{
  var json=req.body;
  response.status(200);
  job(json,response);
});

//app.use(require('express-static')('./'));
const PORT = process.env.PORT || 3000;
app.listen(PORT,()=>{console.log("Server running");});
