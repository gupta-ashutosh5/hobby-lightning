import React, {Component} from 'react';

class EmailAddressForm extends Component{
  constructor(props) {
    super(props);
    this.email = React.createRef();
    this.password = React.createRef();
    this.state = {
      email: '',
      password: '',
      showErrors: false
    };
    this.login = this.login.bind(this);
    this.saveQuizData = this.saveQuizData.bind(this);
    this.showRegister = this.showRegister.bind(this);
  }

  saveQuizData() {
    this.props.onLoginSuccess();
  }

  login(e) {
   var email = this.email.current.value;
   var pass = this.password.current.value;
   var afterLogin = (uid) => {
     this.props.onLoginSuccess(e, uid);
   };
    console.log(this.state.email);
    fetch('/session/token')
      .then(function(response) {
        return response.text();
      })
      .then(function(token) {
        fetch('/user/login?_format=json', {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/hal+json',
            'X-CSRF-Token': token,
            'Accept': 'application/hal+json'
          },
          body: JSON.stringify({
            name: email,
            pass: pass
          })
        }).then((response) => {
          response.json().then((data) => {
            if (response.ok) {
              if (data.current_user.uid > 0 && data.current_user.name === email) {
                afterLogin(data.current_user.uid);
              }
            }
            else {
              var errorsDiv = document.getElementById('quiz-login-errors');
              var errorMessage = data.message.trim().split(/\r?\n/);
              errorsDiv.innerText = (typeof errorMessage[1] !== 'undefined')
                ? errorMessage[errorMessage.length-1].split(':')[1].trim()
                : errorMessage;
              this.setState({
                showErrors: true
              });
            }
          });
        });
      }.bind(this));
  }

  showRegister(e) {
    this.props.showRegister(e, false);
  }

  render() {
    return (
    <div className={(this.props.showLoginForm) ? 'quiz-login-form-wrapper' : 'hidden'}>
      <h4>
        Kindly login to view answer
      </h4>
      <form name="emailForm" className="emailForm">
        <input type="text" ref={this.email} placeholder="Enter email address"/>
        <input type="password" ref={this.password} placeholder="Enter password"/>
        <input type="button" value="Login" onClick={(e) => {this.login(e)}}/>
      </form>
      <div id='quiz-login-errors' className={(this.state.showErrors) ? 'quiz-error-wrapper' : 'hidden'}>
        &nbsp;
      </div>
      <div onClick={this.showRegister.bind(this)} style={{cursor: 'pointer'}}>
        New user? Sign up to view answers.
      </div>
    </div>
    );
  }
}


export default EmailAddressForm;
