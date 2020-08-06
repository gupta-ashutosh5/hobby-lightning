import React, {Component} from 'react';

var errorsDiv = '';
class UserRegistrationForm extends Component{
  constructor(props) {
    super(props);
    this.email = React.createRef();
    this.password = React.createRef();
    this.confirmPassword = React.createRef();
    this.state = {
      email: '',
      password: '',
      showErrors: false
    };
    this.login = this.login.bind(this);
    this.saveQuizData = this.saveQuizData.bind(this);
    this.showLogin = this.showLogin.bind(this);
  }

  saveQuizData() {
    this.props.onLoginSuccess();
  }

  login(e) {
    var email = this.email.current.value;
    var pass = this.password.current.value;
    var confirmPass = this.confirmPassword.current.value;

    if (pass === confirmPass) {
      var afterLogin = (uid) => {
        this.props.onLoginSuccess(e, uid);
      };
      console.log(this.state.email);
      fetch('/session/token')
        .then(function(response) {
          return response.text();
        })
        .then(function(token) {
          fetch('/user/register?_format=json', {
            method: 'POST',
            credentials: 'include',
            headers: {
              'Content-Type': 'application/hal+json',
              'X-CSRF-Token': token,
              'Accept': 'application/hal+json'
            },
            body: JSON.stringify({
              _links: {
                type: {
                  href: "http://upscwala-lightning.dd:8083/rest/type/user/user"
                }
              },
              name: {
                value: email
              },
              mail: {
                value: email
              },
              pass: {
                value: pass
              }
            })
          }).then((response) => {
            response.json().then((data) => {
              if (response.ok) {
                console.log(data);
                if (data.uid[0].value > 0 && data.name[0].value === email) {
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
                  }).then((loginResponse) => {
                    if (loginResponse.ok) {
                      loginResponse.json().then((loginData) => {
                        if (loginResponse.ok) {
                          if (loginData.current_user.uid > 0 && loginData.current_user.name === email) {
                            afterLogin(loginData.current_user.uid);
                          }
                        }
                      });
                    }
                    else {
                      console.log('error sending data');
                    }
                  });
                }
              }
              else {
                errorsDiv = document.getElementById('quiz-register-errors');
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
    else {
      errorsDiv = document.getElementById('quiz-register-errors');
      errorsDiv.innerText = 'Passwords do not match.';
      this.setState({
        showErrors: true
      });
    }
  }

  showLogin(e) {
    this.props.showLogin(e, true);
  }

  render() {
    return (
      <div className={(this.props.showLoginForm) ? 'hidden': 'quiz-register-form-wrapper'}>
        <h3>
          Sign up
        </h3>
        <form id="register-form" name="registerForm" className="registerForm">
          <input type="text" ref={this.email} placeholder="Enter email address"/>
          <input type="password" ref={this.password} placeholder="Enter password"/>
          <input type="password" ref={this.confirmPassword} placeholder="Renter password"/>
          <input type="button" value="Sign up" onClick={(e) => {this.login(e)}}/>
        </form>
        <div id='quiz-register-errors' className={(this.state.showErrors) ? 'quiz-error-wrapper' : 'hidden'}>
           &nbsp;
        </div>
        <div onClick={this.showLogin.bind(this)} style={{cursor: 'pointer'}}>
          Registered user? Please login to continue.
        </div>
      </div>
    );
  }
}


export default UserRegistrationForm;
