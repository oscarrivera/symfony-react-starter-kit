import { createRoot } from 'react-dom/client'
import { Root } from './App'
import './styles.css'

const el = document.getElementById('root')
if (!el) {
  throw new Error('Missing #root')
}

createRoot(el).render(<Root />)
