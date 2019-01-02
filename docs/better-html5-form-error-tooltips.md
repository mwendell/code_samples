# Better HTML5 Form Error Tooltips

With the advent of HTML5, we now have a simple way to denote that a form field must be completed to submit the form, the 'required' attribute.

```
<input type='text' name='last_name' required />
```

When the user attempts to submit a form with this information missing, the browser itself will throw an error and prevent the action.

![alt text](https://github.com/mwendell/code_samples/blob/master/docs/images/better-html5-form-error-tooltips-a.png "Screenshot")


As you can see however, the default message gives no context, and is identical for each field. While it's obvious what the user needs to do in most cases, there is a clear UX benefit to customizing the error messages to make them more precise, more friendly, and more constructive. 

So can we change it? Yes, yes we can.

```
<input type='text' name='last_name' oninvalid="this.setCustomValidity(this.willValidate ? '' : 'Please enter your last name.')" required />
```

And here's what that looks like practice:

![alt text](https://github.com/mwendell/code_samples/blob/master/docs/images/better-html5-form-error-tooltips-b.png "Screenshot")


While, again, this example does not exploit custom error messages to their fullest potential, hopefully you agree that it is more friendly and precise than the default message. Additionally, moving forward with more complex user interactions, we should be able to take advantage of this function, along with well written user prompts, to increase user confidence in our products and decrease support costs for our clients.

More Info:

Nielsen Norman Group: [Error Message Guidelines](https://www.nngroup.com/articles/error-message-guidelines/)
W3 Schools [Javascript oninvalid Event](http://www.w3schools.com/jsref/event_oninvalid.asp)
