import React, {Component} from 'react';
import PropTypes from 'prop-types';

class CaptureUserModal extends Component{
  render() {
    // Return nothing in case show is false.
    if (!this.props.show) {
      return null;
    }

    // The gray background.
    const backdropStyle = {
      position: 'fixed',
      top: 0,
      bottom: 0,
      left: 0,
      right: 0,
      backgroundColor: 'rgba(0,0,0,0.3)',
      padding: 50
    };

    // The modal "window"
    const modalStyle = {
      backgroundColor: '#fff',
      borderRadius: 5,
      maxWidth: 500,
      minHeight: 300,
      margin: '0 auto',
      padding: 30
    };

    return (
      <div className="backdrop" style={backdropStyle}>
        <div className="modal" style={modalStyle}>
          <div onClick={this.props.onClose} style={{float: 'right', cursor: 'pointer'}}>X</div>
          {this.props.children}
        </div>
      </div>
    )

  }
}

CaptureUserModal.propTypes = {
  onClose: PropTypes.func.isRequired,
  show: PropTypes.bool,
  children: PropTypes.node
};

export default CaptureUserModal;
